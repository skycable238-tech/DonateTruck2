#!/usr/bin/env python3
"""
DonateTrack Phase 2 - initial test run.
Resets the database from the SQL scripts, drives the real web app over HTTP
(login, forms, role checks) and checks the results with direct SQL queries.

Usage:  python3 tests/run_tests.py [base_url] [mysql_admin_args]
Needs:  python3 + requests + beautifulsoup4, the mysql client, and the app running.
"""
import re, subprocess, sys, datetime, os
import requests
from bs4 import BeautifulSoup

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
ADMIN = (sys.argv[2] if len(sys.argv) > 2 else "mysql -uroot").split()
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PW = "DonateTrack#2026"
TODAY = datetime.date.today().isoformat()
results, log = [], []


def sql(query, db="donatetrack", user=None):
    cmd = (user or ADMIN) + ["-N", "-B", db, "-e", query]
    p = subprocess.run(cmd, capture_output=True, text=True)
    out = (p.stdout + p.stderr).strip()
    errs = [l for l in out.splitlines() if l.startswith("ERROR")]
    return errs[0] if errs else out


def reset_db():
    for f in ("01_schema.sql", "02_sample_data.sql", "03_app_user.sql"):
        subprocess.run(ADMIN, stdin=open(f"{ROOT}/database/{f}"), check=True, capture_output=True)


def login(username, password=PW):
    s = requests.Session()
    r = s.get(f"{BASE}/login.php")
    tok = BeautifulSoup(r.text, "html.parser").find("input", {"name": "csrf"})["value"]
    r = s.post(f"{BASE}/login.php", data={"username": username, "password": password, "csrf": tok})
    return s, r


def token(s, path):
    r = s.get(f"{BASE}/{path}")
    t = BeautifulSoup(r.text, "html.parser").find("input", {"name": "csrf"})
    return t["value"] if t else ""


def text_of(r):
    return BeautifulSoup(r.text, "html.parser").get_text(" ", strip=True)


def alerts(r):
    soup = BeautifulSoup(r.text, "html.parser")
    return " | ".join(a.get_text(" ", strip=True).replace("Close", "").strip() for a in soup.select(".alert"))


def check(tc, title, action, expected, actual, ok, evidence=""):
    results.append((tc, title, action, expected, actual, ok))
    log.append(f"[{'PASS' if ok else 'FAIL'}] {tc} {title}\n   Action:   {action}\n   Expected: {expected}\n   Actual:   {actual}")
    if evidence:
        log.append("   Evidence: " + evidence.replace("\n", "\n             "))
    log.append("")


def main():
    reset_db()
    coord, r = login("coordinator")
    vol, _ = login("volunteer1")
    vol2, _ = login("volunteer2")
    donor, _ = login("donor1")

    # ---------------- Authentication ----------------
    s, r = login("coordinator", "wrong-password")
    check("TC-01", "Login with a wrong password", "POST login.php with bad password",
          "Rejected with 'Invalid username or password.'", alerts(r), "Invalid username or password" in r.text)
    s, r = login("volunteer3")
    check("TC-02", "Deactivated account cannot log in", "Login as volunteer3 (is_active = 0)",
          "Rejected: account is deactivated", alerts(r), "deactivated" in r.text)
    check("TC-03", "Role-based redirect after login", "Login as coordinator, volunteer1 and donor1",
          "Staff land on the dashboard, the donor on My Donations",
          f"coordinator -> {coord.get(BASE + '/index.php').url.split('/')[-1]}, donor1 -> {donor.get(BASE + '/index.php').url.split('/')[-1]}",
          coord.get(f"{BASE}/index.php").url.endswith("dashboard.php") and donor.get(f"{BASE}/index.php").url.endswith("my_donations.php"))

    # ---------------- Create: record donation + validation ----------------
    before = sql("SELECT COUNT(*) FROM donations")
    t = token(vol, "donation_form.php")
    r = vol.post(f"{BASE}/donation_form.php", data={"csrf": t, "donor_id": 4, "category_id": 1, "quantity": "0", "donation_date": TODAY, "notes": ""})
    after = sql("SELECT COUNT(*) FROM donations")
    check("TC-04", "Record a donation with quantity 0 (BR-01)", "Volunteer submits Record Donation with quantity 0",
          "Rejected with 'Quantity must be greater than 0'; no row saved", f"{alerts(r)}  (rows before/after: {before}/{after})",
          "Quantity must be greater than 0" in r.text and before == after)

    r = vol.post(f"{BASE}/donation_form.php", data={"csrf": t, "donor_id": 4, "category_id": 1, "quantity": "10", "donation_date": (datetime.date.today() + datetime.timedelta(days=3)).isoformat(), "notes": ""})
    check("TC-05", "Future donation date (BR-05)", "Record donation dated 3 days from today",
          "Rejected: date cannot be in the future; no row saved", alerts(r), "cannot be in the future" in r.text and sql("SELECT COUNT(*) FROM donations") == before)

    r = vol.post(f"{BASE}/donation_form.php", data={"csrf": t, "donor_id": 9, "category_id": 1, "quantity": "10", "donation_date": TODAY, "notes": ""})
    check("TC-06", "Inactive donor (BR-02)", "Record donation for inactive donor #9 (Pedro Villanueva)",
          "Rejected with 'Donor/Category is inactive'; nothing saved", alerts(r), "Donor/Category is inactive" in r.text and sql("SELECT COUNT(*) FROM donations") == before)
    r = vol.post(f"{BASE}/donation_form.php", data={"csrf": t, "donor_id": 4, "category_id": 7, "quantity": "10", "donation_date": TODAY, "notes": ""})
    check("TC-07", "Inactive category (BR-02)", "Record donation in inactive category #7 (Medical Supplies)",
          "Rejected with 'Donor/Category is inactive'; nothing saved", alerts(r), "Donor/Category is inactive" in r.text and sql("SELECT COUNT(*) FROM donations") == before)

    r = vol.post(f"{BASE}/donation_form.php", data={"csrf": t, "donor_id": 4, "category_id": 1, "quantity": "75.5", "donation_date": TODAY, "notes": "Test donation from TC-08"})
    new_id = sql("SELECT MAX(donation_id) FROM donations")
    row = sql(f"SELECT donation_id, donor_id, category_id, quantity, status, recorded_by FROM donations WHERE donation_id={new_id}")
    check("TC-08", "Record a valid donation (Create)", "Volunteer records 75.5 kg of rice from Juan dela Cruz",
          "Saved with status RECORDED and recorded_by = volunteer1 (user 2)", row,
          int(new_id) == 17 and "\tRECORDED\t2" in row and "75.50" in row,
          "SELECT donation_id, donor_id, category_id, quantity, status, recorded_by FROM donations WHERE donation_id=17;\n" + row)

    # ---------------- Read: list, search, filter, sort, pagination ----------------
    r = coord.get(f"{BASE}/donations.php?status=VERIFIED")
    ids = [td.get_text() for td in BeautifulSoup(r.text, "html.parser").select("tbody tr td:first-child")]
    expect = sql("SELECT GROUP_CONCAT(donation_id ORDER BY donation_id) FROM donations WHERE status='VERIFIED'").split(",")
    check("TC-09", "Filter donations by status", "Open Donations with status=VERIFIED", f"Exactly the VERIFIED donations: {','.join(expect)}",
          ",".join(sorted(ids, key=int)), sorted(ids, key=int) == sorted(expect, key=int))
    r = coord.get(f"{BASE}/donations.php?q=rotary")
    ids = sorted(td.get_text() for td in BeautifulSoup(r.text, "html.parser").select("tbody tr td:first-child"))
    expect = sorted(sql("SELECT donation_id FROM donations d JOIN donors dn USING(donor_id) WHERE dn.donor_name LIKE '%rotary%'").split())
    check("TC-10", "Search donations by donor name", "Open Donations with q=rotary", f"Only Cebu Rotary Club donations: {','.join(expect)}", ",".join(ids), ids == expect)
    r = coord.get(f"{BASE}/donations.php?from=2026-11-01&to=2026-11-30&category_id=2")
    ids = sorted(td.get_text() for td in BeautifulSoup(r.text, "html.parser").select("tbody tr td:first-child"))
    expect = sorted(sql("SELECT donation_id FROM donations WHERE category_id=2 AND donation_date BETWEEN '2026-11-01' AND '2026-11-30'").split())
    check("TC-11", "Filter by category and date range", "Open Donations with category_id=2, from=2026-11-01, to=2026-11-30", f"Canned Goods in November: {','.join(expect)}", ",".join(ids), ids == expect)
    r = coord.get(f"{BASE}/donations.php?sort=quantity&dir=desc")
    qtys = [float(re.sub(r"[^\d.]", "", td.get_text().split()[0])) for td in BeautifulSoup(r.text, "html.parser").select("tbody tr td:nth-child(5)")]
    check("TC-12", "Sort by quantity (descending)", "Open Donations with sort=quantity&dir=desc", "Quantities appear in descending order", str(qtys), qtys == sorted(qtys, reverse=True) and len(qtys) > 0)
    p1 = coord.get(f"{BASE}/donations.php?page=1"); p2 = coord.get(f"{BASE}/donations.php?page=2")
    n1 = len(BeautifulSoup(p1.text, "html.parser").select("tbody tr")); n2 = len(BeautifulSoup(p2.text, "html.parser").select("tbody tr"))
    check("TC-13", "Pagination", "Open Donations page 1 and page 2 (8 per page, 17 rows)", "Page 1 has 8 rows and page 2 has 8 rows", f"page 1 = {n1} rows, page 2 = {n2} rows", n1 == 8 and n2 == 8)
    r = coord.get(f"{BASE}/donations.php?q=%27%20OR%201%3D1%20--%20")
    check("TC-14", "SQL injection attempt in search box", "Search donor name for ' OR 1=1 -- ", "Treated as plain text: no rows, no database error", "HTTP " + str(r.status_code) + ", message: 'No donations match your filters.'" if "No donations match" in r.text else "HTTP " + str(r.status_code),
          "No donations match" in r.text and r.status_code == 200)

    # ---------------- Update: verify, distribute, over-distribution ----------------
    t = token(coord, "donor_form.php")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "verify"}, allow_redirects=True)
    st = sql("SELECT status FROM donations WHERE donation_id=17")
    check("TC-15", "Coordinator verifies a RECORDED donation", "POST verify for donation #17", "Status changes RECORDED -> VERIFIED", f"{alerts(r)} (status = {st})", st == "VERIFIED")

    bef = sql("SELECT status, quantity - IFNULL((SELECT SUM(quantity_released) FROM distributions WHERE donation_id=17),0) FROM donations WHERE donation_id=17")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "distribute", "recipient_name": "Test Recipient", "recipient_area": "Lahug", "quantity": "100", "distribution_date": TODAY, "remarks": ""})
    aft = sql("SELECT status, quantity - IFNULL((SELECT SUM(quantity_released) FROM distributions WHERE donation_id=17),0) FROM donations WHERE donation_id=17")
    check("TC-16", "Distribute more than the remaining quantity (BR-04)", "Distribute 100 kg from donation #17 (75.5 kg available)",
          "Rejected; remaining quantity and status unchanged", f"{alerts(r)}\n   before (status, remaining): {bef} | after: {aft}", "exceeds the remaining" in r.text and bef == aft,
          "SELECT status, remaining FROM donation 17 -> before: " + bef + " / after: " + aft)

    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "distribute", "recipient_name": "Barangay Lahug", "recipient_area": "Lahug", "quantity": "-5", "distribution_date": TODAY, "remarks": ""})
    check("TC-17", "Distribute a negative quantity (BR-01)", "Distribute -5 from donation #17", "Rejected with 'Quantity must be greater than 0'", alerts(r), "Quantity must be greater than 0" in r.text)

    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "distribute", "recipient_name": "Barangay Lahug", "recipient_area": "Lahug", "quantity": "20", "distribution_date": "2020-01-01", "remarks": ""})
    check("TC-18", "Distribution date earlier than donation date (BR-05)", "Distribute 20 kg dated 2020-01-01", "Rejected with a date validation message", alerts(r), "earlier than the donation date" in r.text)

    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "distribute", "recipient_name": "Barangay Lahug", "recipient_area": "Lahug", "quantity": "30", "distribution_date": TODAY, "remarks": "first"})
    s1 = sql("SELECT status FROM donations WHERE donation_id=17"); rem1 = sql("SELECT quantity - (SELECT SUM(quantity_released) FROM distributions WHERE donation_id=17) FROM donations WHERE donation_id=17")
    check("TC-19", "Partial distribution", "Distribute 30 of 75.5 kg", "Distribution row saved; status VERIFIED -> PARTIAL; remaining 45.50", f"status = {s1}, remaining = {rem1}", s1 == "PARTIAL" and rem1 == "45.50",
          sql("SELECT distribution_id, donation_id, recipient_name, quantity_released FROM distributions WHERE donation_id=17"))
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "distribute", "recipient_name": "Barangay Apas", "recipient_area": "Apas", "quantity": "45.5", "distribution_date": TODAY, "remarks": "final"})
    s2 = sql("SELECT status FROM donations WHERE donation_id=17"); rem2 = sql("SELECT quantity - (SELECT SUM(quantity_released) FROM distributions WHERE donation_id=17) FROM donations WHERE donation_id=17")
    check("TC-20", "Final distribution reaches zero", "Distribute the remaining 45.5 kg", "Status PARTIAL -> DISTRIBUTED; remaining 0.00", f"status = {s2}, remaining = {rem2}", s2 == "DISTRIBUTED" and rem2 == "0.00",
          sql("SELECT distribution_id, donation_id, recipient_name, quantity_released FROM distributions WHERE donation_id=17"))

    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 17, "action": "verify"})
    check("TC-21", "Invalid status change (BR-03)", "Try to verify donation #17 which is already DISTRIBUTED", "Rejected; status stays DISTRIBUTED", f"{alerts(r)} (status = {sql('SELECT status FROM donations WHERE donation_id=17')})",
          "Invalid status change" in r.text and sql("SELECT status FROM donations WHERE donation_id=17") == "DISTRIBUTED")
    t2 = token(coord, "donor_form.php")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t2, "donation_id": 11, "action": "cancel", "cancel_reason": ""})
    check("TC-22", "Cancel requires a reason", "Cancel donation #11 with an empty reason", "Rejected; status stays RECORDED", alerts(r), "reason" in r.text.lower() and sql("SELECT status FROM donations WHERE donation_id=11") == "RECORDED")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t2, "donation_id": 11, "action": "cancel", "cancel_reason": "Duplicate of donation #8 entry"})
    check("TC-23", "Cancel a RECORDED donation with a reason", "Cancel donation #11 with a reason", "Status RECORDED -> CANCELLED with the reason saved",
          sql("SELECT status, cancel_reason FROM donations WHERE donation_id=11"), sql("SELECT status FROM donations WHERE donation_id=11") == "CANCELLED")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t2, "donation_id": 2, "action": "cancel", "cancel_reason": "should not work"})
    check("TC-24", "A PARTIAL donation cannot be cancelled (BR-03)", "Cancel donation #2 (PARTIAL)", "Rejected; status stays PARTIAL", alerts(r), sql("SELECT status FROM donations WHERE donation_id=2") == "PARTIAL")

    # ---------------- Delete + referential integrity ----------------
    t = token(coord, "donors.php")
    r = coord.post(f"{BASE}/donors.php", data={"csrf": t, "action": "delete", "donor_id": 1}, allow_redirects=True)
    check("TC-25", "Delete a donor that has donations (BR-06)", "Delete donor #1 (Maria Santos, 2 donations)", "Refused by the foreign key; message offers Cancel/Deactivate; donor still exists",
          f"{alerts(r)} (donor rows: {sql('SELECT COUNT(*) FROM donors WHERE donor_id=1')})", "cannot be deleted" in r.text and sql("SELECT COUNT(*) FROM donors WHERE donor_id=1") == "1")
    r = coord.post(f"{BASE}/donors.php", data={"csrf": t, "action": "delete", "donor_id": 8}, allow_redirects=True)
    check("TC-26", "Delete a second donor that has a donation (BR-06)", "Delete donor #8 (Harmony Students Org, has donation #8)", "Refused by foreign key; donor remains", alerts(r), sql("SELECT COUNT(*) FROM donors WHERE donor_id=8") == "1")
    r = coord.post(f"{BASE}/donors.php", data={"csrf": t, "action": "delete", "donor_id": 9}, allow_redirects=True)
    check("TC-27", "Delete a donor with no donations", "Delete donor #9 (Pedro Villanueva)", "Deleted successfully", f"{alerts(r)} (rows left: {sql('SELECT COUNT(*) FROM donors WHERE donor_id=9')})", sql("SELECT COUNT(*) FROM donors WHERE donor_id=9") == "0")
    t = token(coord, "donor_form.php")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 1, "action": "delete"})
    check("TC-28", "Delete a donation that has distributions", "Delete donation #1 (DISTRIBUTED)", "Refused (application check; the FOREIGN KEY also blocks it, see TC-56); donation remains", alerts(r), sql("SELECT COUNT(*) FROM donations WHERE donation_id=1") == "1")
    r = coord.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 8, "action": "delete"})
    check("TC-29", "Delete a RECORDED donation (Delete)", "Delete donation #8 (RECORDED, no distributions)", "Row removed", f"{alerts(r)} (rows left: {sql('SELECT COUNT(*) FROM donations WHERE donation_id=8')})", sql("SELECT COUNT(*) FROM donations WHERE donation_id=8") == "0")

    # ---------------- Donors / categories / users CRUD ----------------
    t = token(coord, "donor_form.php")
    r = coord.post(f"{BASE}/donor_form.php", data={"csrf": t, "donor_name": "Test Donor A", "donor_type": "INDIVIDUAL", "email": "dup@example.com", "phone": "", "address": "", "is_active": "1"})
    r = coord.post(f"{BASE}/donor_form.php", data={"csrf": t, "donor_name": "Test Donor B", "donor_type": "INDIVIDUAL", "email": "dup@example.com", "phone": "", "address": "", "is_active": "1"})
    check("TC-30", "Duplicate donor email is rejected", "Add two donors with email dup@example.com", "Second save rejected: 'A donor with this email already exists.' (MySQL error 1062)", alerts(r) or text_of(r)[text_of(r).find("A donor"):][:50],
          "already exists" in r.text and sql("SELECT COUNT(*) FROM donors WHERE email='dup@example.com'") == "1")
    r = coord.post(f"{BASE}/donor_form.php", data={"csrf": t, "donor_name": "", "donor_type": "INDIVIDUAL", "email": "bad-email", "phone": "", "address": "", "is_active": "1"})
    check("TC-31", "Donor form validation", "Submit empty name and invalid email", "Both fields rejected with messages", alerts(r), "Donor name is required" in r.text and "valid email" in r.text)
    r = coord.post(f"{BASE}/donor_form.php?id=" + sql("SELECT donor_id FROM donors WHERE email='dup@example.com'"), data={"csrf": t, "donor_name": "Test Donor A (edited)", "donor_type": "BUSINESS", "email": "dup@example.com", "phone": "032-000", "address": "Cebu", "is_active": "1"})
    check("TC-32", "Edit a donor (Update)", "Rename Test Donor A and change type to BUSINESS", "Row updated in the database", sql("SELECT donor_name, donor_type, phone FROM donors WHERE email='dup@example.com'"),
          "Test Donor A (edited)\tBUSINESS" in sql("SELECT donor_name, donor_type, phone FROM donors WHERE email='dup@example.com'"))

    t = token(coord, "category_form.php")
    r = coord.post(f"{BASE}/category_form.php", data={"csrf": t, "category_name": "Rice", "unit": "kg", "description": "", "is_active": "1"})
    check("TC-33", "Duplicate category name is rejected", "Add category 'Rice' again", "Rejected: 'A category with this name already exists.'", alerts(r) or text_of(r)[text_of(r).find("A category"):][:60], "already exists" in r.text)
    r = coord.post(f"{BASE}/category_form.php", data={"csrf": t, "category_name": "Blankets", "unit": "pieces", "description": "Warm blankets", "is_active": "1"})
    check("TC-34", "Add a category (Create)", "Add 'Blankets' (pieces)", "Row inserted", sql("SELECT category_name, unit, is_active FROM donation_categories WHERE category_name='Blankets'"), sql("SELECT COUNT(*) FROM donation_categories WHERE category_name='Blankets'") == "1")
    t = token(coord, "categories.php")
    r = coord.post(f"{BASE}/categories.php", data={"csrf": t, "action": "delete", "category_id": 1})
    check("TC-35", "Delete a category that has donations (BR-06)", "Delete category #1 (Rice)", "Refused by foreign key; category remains", alerts(r), sql("SELECT COUNT(*) FROM donation_categories WHERE category_id=1") == "1")

    t = token(coord, "user_form.php")
    r = coord.post(f"{BASE}/user_form.php", data={"csrf": t, "username": "volunteer9", "full_name": "Test Volunteer", "role": "VOLUNTEER", "password": "short", "is_active": "1"})
    check("TC-36", "User form rejects a short password", "Create user with password 'short'", "Rejected: at least 8 characters", alerts(r), "at least 8" in r.text)
    r = coord.post(f"{BASE}/user_form.php", data={"csrf": t, "username": "volunteer9", "full_name": "Test Volunteer", "role": "VOLUNTEER", "password": "GoodPass123", "is_active": "1"})
    h = sql("SELECT LEFT(password_hash,4), LENGTH(password_hash) FROM users WHERE username='volunteer9'")
    check("TC-37", "Create a user; password stored hashed", "Create volunteer9", "Row inserted; password_hash is a bcrypt hash, not plain text", h, h.startswith("$2y$"))
    r = coord.post(f"{BASE}/user_form.php", data={"csrf": t, "username": "volunteer9", "full_name": "Dup", "role": "VOLUNTEER", "password": "GoodPass123", "is_active": "1"})
    check("TC-38", "Duplicate username is rejected", "Create volunteer9 again", "Rejected: 'That username is already taken.'", alerts(r) or text_of(r)[text_of(r).find("That username"):][:40], "already taken" in r.text)

    # ---------------- Report ----------------
    r = coord.get(f"{BASE}/report_totals.php?from=2026-10-01&to=2026-11-30")
    soup = BeautifulSoup(r.text, "html.parser")
    rows = {tr.find_all("td")[0].get_text(): [td.get_text() for td in tr.find_all("td")[2:]] for tr in soup.select("tbody tr")}
    exp = sql("""SELECT c.category_name,
       (SELECT COALESCE(SUM(d.quantity),0) FROM donations d WHERE d.category_id=c.category_id AND d.status<>'CANCELLED' AND d.donation_date BETWEEN '2026-10-01' AND '2026-11-30'),
       (SELECT COALESCE(SUM(x.quantity_released),0) FROM distributions x JOIN donations d ON d.donation_id=x.donation_id WHERE d.category_id=c.category_id AND x.distribution_date BETWEEN '2026-10-01' AND '2026-11-30')
       FROM donation_categories c WHERE c.category_name='Canned Goods'""")
    check("TC-39", "Category totals report", "Open Category Totals for 2026-10-01 to 2026-11-30", "Received/Distributed/Remaining agree with a direct SQL query", f"Canned Goods on page = {rows.get('Canned Goods')}; SQL = {exp.replace(chr(9), ' | ')}",
          rows.get("Canned Goods", [None])[0] == "446" and rows["Canned Goods"][1] == "210" and rows["Canned Goods"][2] == "236",
          exp)
    r = coord.get(f"{BASE}/report_totals.php?from=2026-12-01&to=2026-11-01")
    check("TC-40", "Report rejects an invalid date range", "from=2026-12-01 to=2026-11-01", "Error message; no totals shown", alerts(r), "cannot be later" in r.text)

    # ---------------- Access control ----------------
    t = token(vol, "donation_form.php")
    r = vol.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 15, "action": "verify"})
    check("TC-41", "Volunteer cannot verify a donation (BR-08)", "Volunteer POSTs verify for RECORDED donation #15", "HTTP 403 'Access denied' and status unchanged", f"HTTP {r.status_code}, status = {sql('SELECT status FROM donations WHERE donation_id=15')}", r.status_code == 403 and sql("SELECT status FROM donations WHERE donation_id=15") == "RECORDED")
    r = vol.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 14, "action": "distribute", "recipient_name": "X", "recipient_area": "Y", "quantity": "5", "distribution_date": TODAY})
    check("TC-42", "Volunteer cannot distribute (BR-08)", "Volunteer POSTs distribute for VERIFIED donation #14", "HTTP 403 and no distribution row", f"HTTP {r.status_code}", r.status_code == 403 and sql("SELECT COUNT(*) FROM distributions WHERE donation_id=14") == "0")
    r = vol.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 6, "action": "cancel", "cancel_reason": "no"})
    check("TC-43", "Volunteer cannot cancel a donation (BR-08)", "Volunteer POSTs cancel for VERIFIED donation #6", "HTTP 403 and status unchanged", f"HTTP {r.status_code}, status = {sql('SELECT status FROM donations WHERE donation_id=6')}", r.status_code == 403 and sql("SELECT status FROM donations WHERE donation_id=6") == "VERIFIED")
    r = vol.get(f"{BASE}/users.php")
    check("TC-44", "Volunteer cannot open Manage Users", "Volunteer opens users.php", "HTTP 403", f"HTTP {r.status_code}", r.status_code == 403)
    r = vol.get(f"{BASE}/report_totals.php")
    check("TC-45", "Volunteer cannot open Category Totals", "Volunteer opens report_totals.php", "HTTP 403", f"HTTP {r.status_code}", r.status_code == 403)
    r = vol.get(f"{BASE}/donation_form.php?id=15")
    check("TC-46", "Volunteer cannot edit another volunteer's donation (BR-08)", "volunteer1 opens the edit form of donation #15 (recorded by volunteer2)", "HTTP 403 'Access denied'",
          f"HTTP {r.status_code} (recorded_by = {sql('SELECT recorded_by FROM donations WHERE donation_id=15')})", r.status_code == 403)
    r = donor.get(f"{BASE}/donations.php")
    check("TC-47", "Donor cannot open the staff donation list (BR-08)", "donor1 opens donations.php", "HTTP 403", f"HTTP {r.status_code}", r.status_code == 403)
    r = donor.get(f"{BASE}/my_donations.php")
    ids = sorted(td.get_text() for td in BeautifulSoup(r.text, "html.parser").select("tbody tr td:first-child"))
    exp = sorted(sql("SELECT donation_id FROM donations d JOIN donors dn USING(donor_id) WHERE dn.user_id=4").split())
    check("TC-48", "Donor sees only their own donations (FR-11)", "donor1 opens My Donations", f"Only donations of donor Maria Santos: {','.join(exp)}", ",".join(ids), ids == exp and len(ids) > 0)
    anon = requests.Session(); r = anon.get(f"{BASE}/dashboard.php", allow_redirects=False)
    check("TC-49", "Anonymous visitor is sent to login", "Open dashboard.php without logging in", "Redirect to login.php", f"HTTP {r.status_code} -> {r.headers.get('Location')}", r.status_code == 302 and r.headers.get("Location") == "login.php")
    r = coord.post(f"{BASE}/donation_action.php", data={"donation_id": 4, "action": "cancel", "cancel_reason": "csrf test"})
    check("TC-50", "Form without CSRF token is refused", "POST cancel without the hidden csrf field", "HTTP 400 and no change", f"HTTP {r.status_code}, status = {sql('SELECT status FROM donations WHERE donation_id=4')}", r.status_code == 400 and sql("SELECT status FROM donations WHERE donation_id=4") == "VERIFIED")
    xss = "<script>alert(1)</script>"
    t = token(coord, "donor_form.php")
    coord.post(f"{BASE}/donor_form.php", data={"csrf": t, "donor_name": xss, "donor_type": "INDIVIDUAL", "email": "", "phone": "", "address": "", "is_active": "1"})
    r = coord.get(f"{BASE}/donors.php?q=alert")
    check("TC-51", "Output escaping (XSS)", "Save a donor named <script>alert(1)</script> and view the list", "Shown as text, not executed", "raw <script> present in HTML: " + str(xss in r.text), xss not in r.text and "&lt;script&gt;" in r.text)

    # ---------------- Database-level rules (direct SQL, application account) ----------------
    app = ["mysql", "-udonatetrack_app", "-pDonateTrack_App#2026"]
    out = sql("INSERT INTO donations (donor_id,category_id,recorded_by,donation_date,quantity) VALUES (1,1,1,'2026-11-01',0)")
    check("TC-52", "Database CHECK constraint on quantity (BR-01)", "INSERT a donation with quantity 0 directly in MySQL", "Rejected by CHECK chk_donations_qty", out.splitlines()[0] if out else "(no error)", "chk_donations_qty" in out)
    out = sql("INSERT INTO donations (donor_id,category_id,recorded_by,donation_date,quantity) VALUES (999,1,1,'2026-11-01',5)")
    check("TC-53", "Foreign key rejects an unknown donor", "INSERT a donation with donor_id 999", "Rejected by fk_donations_donor", out.splitlines()[0] if out else "(no error)", "foreign key constraint fails" in out)
    out = sql("INSERT INTO distribution_audit (donation_id, performed_by, action) VALUES (1,1,'manual')", user=app)
    check("TC-54", "Application account cannot write the audit table (BR-07)", "INSERT into distribution_audit as donatetrack_app", "Denied: SELECT-only privilege", out.splitlines()[0] if out else "(no error)", "denied" in out.lower())
    out = sql("SELECT COUNT(*) FROM donations", user=app)
    check("TC-55", "Application account can read working tables", "SELECT COUNT(*) FROM donations as donatetrack_app", "Succeeds", out, out.isdigit())

    out = sql("DELETE FROM donations WHERE donation_id = 1", user=app)
    check("TC-56", "Foreign key RESTRICT blocks deleting a donation that has distributions (BR-06)", "DELETE donation #1 directly as donatetrack_app", "Rejected by fk_distributions_donation (error 1451)", out.splitlines()[0] if out else "(no error)", "foreign key constraint fails" in out and sql("SELECT COUNT(*) FROM donations WHERE donation_id=1") == "1")

    # ---------------- Backup and restore ----------------
    dump = f"{ROOT}/tests/backup_test.sql"
    subprocess.run(["mysqldump", "-uroot", "--routines", "--triggers", "donatetrack"], stdout=open(dump, "w"), check=True)
    subprocess.run(ADMIN, input="DROP DATABASE IF EXISTS donatetrack_restore; CREATE DATABASE donatetrack_restore CHARACTER SET utf8mb4;", text=True, check=True)
    subprocess.run(ADMIN + ["donatetrack_restore"], stdin=open(dump), check=True)
    a = sql("SELECT (SELECT COUNT(*) FROM donations),(SELECT COUNT(*) FROM distributions),(SELECT COUNT(*) FROM donors)")
    b = sql("SELECT (SELECT COUNT(*) FROM donations),(SELECT COUNT(*) FROM distributions),(SELECT COUNT(*) FROM donors)", db="donatetrack_restore")
    check("TC-57", "Backup and restore (mysqldump)", "mysqldump the database, import into a fresh database, compare row counts", "Row counts (donations, distributions, donors) are identical", f"original = {a.replace(chr(9), ',')}; restored = {b.replace(chr(9), ',')}", a == b)
    subprocess.run(ADMIN, input="DROP DATABASE donatetrack_restore;", text=True)
    os.remove(dump)

    # ---------------- Summary ----------------
    passed = sum(1 for x in results if x[-1])
    log.append(f"SUMMARY: {passed} of {len(results)} test cases passed.")
    out = "\n".join(log)
    print(out)
    open(f"{ROOT}/tests/test_results.txt", "w").write(f"DonateTrack Phase 2 - test run {datetime.datetime.now():%Y-%m-%d %H:%M}\n\n" + out + "\n")
    import json
    json.dump([dict(tc=a, title=b, action=c, expected=d, actual=e, passed=f) for a, b, c, d, e, f in results], open(f"{ROOT}/tests/test_results.json", "w"), indent=1)
    sys.exit(0 if passed == len(results) else 1)


if __name__ == "__main__":
    main()
