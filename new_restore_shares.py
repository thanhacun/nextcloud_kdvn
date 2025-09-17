#!/usr/bin/env python3
import psycopg2

# ===== CONFIG =====
OLD_DB = {
    "host": "localhost",
    "port": "65432",   # port you mapped old DB to
    "dbname": "nextcloud_database",
    "user": "oc_nextcloud",
    "password": "c86a0114c2c3b00a8d6ef4b0b3c06b1bb31f76c6c2655df7"
}

NEW_DB = {
    "host": "localhost",
    "port": "55432",
    "dbname": "nextcloud",
    "user": "nextcloud",
    "password": "Pg@9071997"
}

# Mapping old usernames → new usernames
USER_MAP = {
    "truong-quang-thanh": "truong-quang-thanh",   # example
    # add more mappings here
}

OUTPUT_FILE = "restore_shares.sql"
# ==================

def connect(dbconf):
    return psycopg2.connect(**dbconf)

def fetch_shares(conn, user):
    cur = conn.cursor()
    cur.execute("SELECT id, share_type, share_with, uid_owner, item_type, file_source, file_target, permissions, stime FROM oc_share WHERE uid_owner = %s;", (user,))
    rows = cur.fetchall()
    cur.close()
    return rows

def fileid_exists(conn, fileid):
    cur = conn.cursor()
    cur.execute("SELECT fileid FROM oc_filecache WHERE fileid = %s;", (fileid,))
    exists = cur.fetchone() is not None
    cur.close()
    return exists

def sql_value(val):
    """Return SQL-safe representation: quoted string, number, or NULL."""
    if val is None:
        return "NULL"
    if isinstance(val, str):
        # Escape single quotes by doubling them
        escaped = val.replace("'", "''")
        return f"'{escaped}'"
    return str(val)

def main():
    old_conn = connect(OLD_DB)
    new_conn = connect(NEW_DB)

    inserts = []

    for old_user, new_user in USER_MAP.items():
        print(f"Processing shares from {old_user} → {new_user}")

        shares = fetch_shares(old_conn, old_user)

        for share in shares:
            (id, share_type, share_with, uid_owner, item_type, file_source, file_target, permissions, stime) = share

            # only import if file still exists in new DB
            if fileid_exists(new_conn, file_source):
               sql = f"""INSERT INTO oc_share
               (share_type, share_with, uid_owner, item_type,
                file_source, file_target, permissions, stime)
               VALUES ({share_type}, {sql_value(share_with)}, {sql_value(new_user)},
                      {sql_value(item_type)}, {file_source}, {sql_value(file_target)},
                      {permissions}, {stime});"""
               inserts.append(sql)

    if inserts:
        with open(OUTPUT_FILE, "w") as f:
            f.write("\n".join(inserts))
        print(f"✅ {len(inserts)} share(s) exported to {OUTPUT_FILE}")
    else:
        print("⚠️ No shares found or matched — check your USER_MAP and fileids.")

    old_conn.close()
    new_conn.close()

if __name__ == "__main__":
    main()
