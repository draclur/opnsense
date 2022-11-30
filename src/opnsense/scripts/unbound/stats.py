#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Deciso B.V.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""

import argparse
import sqlite3
import ujson
import sys
import syslog
import re
import os
from time import time
from operator import itemgetter

class DBWrapper:
    def __init__(self):
        syslog.openlog('unbound', logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)
        self.db_name = "/var/unbound/data/unbound.sqlite"
        self.con = sqlite3.connect(self.db_name)
        self.cursor = self.con.cursor()
        self.exit = False # let the subcommands handle what to return when no db is available

        check = "SELECT name FROM sqlite_master WHERE type='table' AND name='query';"
        res = self.cursor.execute(check)
        if not res.fetchone():
            self.exit = True

    def execute(self, query, params=None):
        try:
            if params:
                self.cursor.execute(query, params)
            else:
                self.cursor.execute(query)
        except sqlite3.DatabaseError as e:
            syslog.syslog(syslog.LOG_ERR, "Unable to execute database operation: %s." % e)

        return self.cursor.fetchall()

    def close(self):
        self.cursor.close()
        self.con.close()

def percent(val, total):
    if val == 0 or total == 0:
        return 0
    return '{:.2f}'.format(round(((val / total) * 100), 2))

def handle_rolling(db, args):
    interval = int(re.sub("^(?:(?!300|60).)*$", "300", str(args.interval)))
    tp = int(re.sub("^(?:(?!24|12|1).)*$", "24", str(args.timeperiod)))

    result = {}

    if not db.exit:
        if args.clients:
            query = """
                WITH grouped AS (
                    SELECT v.start_timestamp s, v.end_timestamp e, c.client cl, COUNT(c.client) cnt_cl
                    FROM v_time_buckets_{intv}min v
                    LEFT JOIN query c ON
                        c.time >= v.start_timestamp AND
                        c.time <= v.end_timestamp
                    WHERE DATETIME(v.start_timestamp, 'unixepoch') > DATETIME('now', '-{tp} hour')
                    GROUP BY
                        v.end_timestamp,
                        c.client
                    ORDER BY
                        v.end_timestamp
                )
                SELECT s, e, GROUP_CONCAT(cl), GROUP_CONCAT(cnt_cl)
                FROM grouped
                GROUP BY
                    e
                ORDER BY
                    e;
            """.format(intv=interval//60, tp=tp)
        else:
            query = """
                SELECT v.start_timestamp, v.end_timestamp, COUNT(q.qid) AS cnt,
                    COUNT(case q.action when 0 then 1 else null end) AS passed,
                    COUNT(case q.action when 1 then 1 else null end) AS blocked,
                    COUNT(case q.action when 2 then 1 else null end) AS dropped,
                    COUNT(case q.response_type when 0 then 1 else null end) AS resolved,
                    COUNT(case q.response_type when 1 then 1 else null end) AS local,
                    COUNT(case q.response_type when 2 then 1 else null end) AS cached
                FROM v_time_buckets_{intv}min v
                LEFT JOIN query q ON
                    q.time >= v.start_timestamp AND
                    q.time <= v.end_timestamp
                WHERE DATETIME(v.start_timestamp, 'unixepoch') > DATETIME('now', '-{tp} hour')
                GROUP BY
                    v.end_timestamp
                ORDER BY
                    v.end_timestamp;
            """.format(intv=(interval//60), tp=tp)

        data = db.execute(query)

        if data:
            if args.clients:
                result = {}
                for row in data:
                    interval = {row[0]: {}}
                    if row[2]:
                        tmp = []
                        counts = row[3].split(',')
                        for idx, client in enumerate(row[2].split(',')):
                            tmp.append((client, int(counts[idx])))
                        # sort the list by most active client
                        tmp.sort(key=itemgetter(1), reverse=True)
                        # limit by 10
                        tmp = tmp[:10]
                        interval[row[0]] |= dict(tmp)
                    result |= interval
            else:
                result = {
                    tup[0]: {
                            'total': tup[2],
                            'passed': tup[3],
                            'blocked': tup[4],
                            'dropped': tup[5],
                            'resolved': tup[6],
                            'local': tup[7],
                            'cached': tup[8]
                        }
                    for tup in data
                }
    print(ujson.dumps(result))

def handle_top(db, args):
    total = blocked = local = passed = blocklist_size = 0
    r_top = r_top_blocked = {}
    start_time = int(time())

    bl_path = '/var/unbound/data/dnsbl.size'
    if os.path.isfile(bl_path) and os.path.getsize(bl_path) > 0:
        with open(bl_path, 'r') as f:
            blocklist_size = int(f.readline())

    if not db.exit:
        # get top N of all passed queries
        top = """
            SELECT domain, COUNT(domain) as cnt
            FROM query
            WHERE action == 0
            GROUP BY domain
            ORDER BY cnt DESC
            LIMIT :max;
        """

        r_top = db.execute(top, {"max": args.max})

        # get top N of all blocked queries
        top_blocked = """
            SELECT domain, COUNT(domain) as cnt, blocklist
            FROM query
            WHERE action == 1
            GROUP BY DOMAIN
            ORDER BY cnt DESC
            LIMIT :max;
        """

        r_top_blocked = db.execute(top_blocked, {"max": args.max})

        # get counters of total values
        total = """
            SELECT COUNT(*) AS total,
                COUNT(case q.action when 1 then 1 else null end) AS blocked,
                COUNT(case q.response_type when 1 then 1 else null end) AS local,
                COUNT(case q.action when 0 then 1 else null end) AS passed
            FROM query q;
        """

        r_total = db.execute(total)

        # get initial start time
        t = """
            SELECT time
            FROM query
            ORDER BY qid ASC
            LIMIT 1;
        """

        r_start_time = db.execute(t)

        if r_total and r_start_time:
            total = r_total[0][0]
            blocked = r_total[0][1]
            local = r_total[0][2]
            passed = r_total[0][3]
            start_time = r_start_time[0][0]

    print(ujson.dumps({
        "total": total,
        "blocklist_size": blocklist_size,
        "passed": passed,
        "blocked": {"total": blocked, "pcnt": percent(blocked, total)},
        "local": {"total": local, "pcnt": percent(local, total)},
        "start_time": start_time,
        "top": {
            k: {
                "total": v,
                "pcnt": percent(v, passed)
            } for k, v in dict(r_top).items()
        },
        "top_blocked": {
            tup[0]: {
                "total": tup[1],
                "pcnt": percent(tup[1], blocked),
                "blocklist": tup[2]
            } for tup in r_top_blocked
        }
    }))

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(dest='command', help='sub-command help')
    r_parser = subparsers.add_parser('rolling', help='get rolling aggregate of query data')
    r_parser.add_argument('--timeperiod', help='timeperiod in hours. Valid values are [24, 12, 1]', type=int, default=24)
    r_parser.add_argument('--interval', help='interval in seconds. valid values are [300, 60]', type=int, default=300)
    r_parser.add_argument('--clients', help='get top 10 client activity instead', action='store_true')
    r_parser.set_defaults(func=handle_rolling)

    t_parser = subparsers.add_parser('totals', help='get top queried domains and total counters')
    t_parser.add_argument('--max', help='limit top queried domains by max items', type=int, default=10)
    t_parser.set_defaults(func=handle_top)

    if len(sys.argv)==1:
        parser.print_help()
        sys.exit(1)

    inputargs = parser.parse_args()
    db = DBWrapper()

    inputargs.func(db, inputargs)

    db.close()
