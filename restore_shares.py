#!/usr/bin/env python3
import re
import psycopg2

# ==== CONFIG ====
OLD_SQL_DUMP = "old_data.sql"   # your dump file
OUTPUT_SQL = "restore_inserts.sql"

# user mapping (edit this)
USER_MAP = {
    "Hoang-thanh-bang": "hoang-thanh-bang",
    "truong-quang-thanh": "truong-quang-thanh"
    # add more mappings here
}

# connect to new Nextcloud database
NEW_DB = {
    "dbname": "nextcloud",
    "user": "nextcloud",
    "password": "Pg@9071997",
    "host": "localhost",
    "port": 55432
}

# regex to catch INSERT lines
insert_re = re.compile(r"INSERT INTO oc_share.*VALUES\s*\((.+)\);", re.IGNORECASE)


def parse_values(value_str):
    """Split SQL values safely (very simple version, assumes no commas inside text)."""
    parts = []
    current = ""
    in_str = False

    for ch in value_str:
        if ch == "'" and not in_str:
            in_str = True
            current += ch
        elif ch == "'" and in_str:
            in_str = False
            current += ch
        elif ch == "," and not in_str:
            parts.append(current.strip())
            current = ""
        else:
            current += ch
    if current:
        parts.append(current.strip())
    return parts


def main():
    # connect to new DB
    conn = psycopg2.connect(**NEW_DB)
    cur = conn.cursor()

    inserts = []

    with open(OLD_SQL_DUMP, "r", encoding="utf-8") as f:
        for line in f:
            match = insert_re.search(line)
            if not match:
                continue

            values = parse_values(match.group(1))

            # unpack based on Nextcloud oc_share structure
            (
                id_, share_type, share_with, password, uid_owner,
                uid_initiator, parent, item_type, item_source,
                item_target, file_source, file_target, permissions,
                stime, accepted, expiration, token, mail_send,
                share_name, password_by_talk, note, hide_download,
                label, attributes, password_expiration_time,
                reminder_sent
            ) = values[:26]

            # remap user
            uid_owner = uid_owner.strip("'")
            new_owner = USER_MAP.get(uid_owner, uid_owner)

            # check if file_source exists in new DB
            cur.execute("SELECT fileid FROM oc_filecache WHERE fileid = %s;", (file_source,))
            row = cur.fetchone()
            if not row:
                print(f"⚠️ Skipping share {id_}, fileid {file_source} not found")
                continue

            # create new insert
            insert_sql = f"""
INSERT INTO oc_share
(id, share_type, share_with, password, uid_owner, uid_initiator, parent,
 item_type, item_source, item_target, file_source, file_target, permissions,
 stime, accepted, expiration, token, mail_send, share_name, password_by_talk,
 note, hide_download, label, attributes, password_expiration_time, reminder_sent)
VALUES (
 {id_}, {share_type}, {share_with}, {password},
 '{new_owner}', {uid_initiator}, {parent},
 {item_type}, {item_source}, {item_target},
 {file_source}, {file_target}, {permissions},
 {stime}, {accepted}, {expiration}, {token}, {mail_send},
 {share_name}, {password_by_talk}, {note}, {hide_download},
 {label}, {attributes}, {password_expiration_time}, {reminder_sent}
);
"""
            inserts.append(insert_sql)

    cur.close()
    conn.close()

    # save to file
    with open(OUTPUT_SQL, "w", encoding="utf-8") as out:
        out.writelines(inserts)

    print(f"✅ Done! Generated {len(inserts)} INSERTs in {OUTPUT_SQL}")


if __name__ == "__main__":
    main()
