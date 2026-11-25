#!/usr/bin/env python3
"""Capture screenshots of the running app (headless Chrome) for the Phase 2 evidence."""
import subprocess, sys, os, datetime, requests
from bs4 import BeautifulSoup
BASE = "http://127.0.0.1:8080"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = sys.argv[1] if len(sys.argv) > 1 else "/home/claude/evidence"
os.makedirs(OUT, exist_ok=True)
PW = "DonateTrack#2026"
TODAY = datetime.date.today().isoformat()

for f in ("01_schema.sql", "02_sample_data.sql", "03_app_user.sql"):
    subprocess.run(["mysql", "-uroot"], stdin=open(f"{ROOT}/database/{f}"), check=True, capture_output=True)

def login(u):
    s = requests.Session()
    t = BeautifulSoup(s.get(f"{BASE}/login.php").text, "html.parser").find("input", {"name": "csrf"})["value"]
    s.post(f"{BASE}/login.php", data={"username": u, "password": PW, "csrf": t})
    return s

def tok(s):
    return BeautifulSoup(s.get(f"{BASE}/donor_form.php").text, "html.parser").find("input", {"name": "csrf"})["value"]

def shot(name, html, h=900):
    html = html.replace("<head>", f'<head><base href="{BASE}/">', 1)
    p = f"/tmp/{name}.html"
    open(p, "w").write(html)
    subprocess.run(["google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu", "--hide-scrollbars",
                    f"--window-size=1280,{h}", f"--screenshot={OUT}/{name}.png", f"file://{p}"], capture_output=True)
    print("saved", name)

anon = requests.Session()
shot("01_login", anon.get(f"{BASE}/login.php").text, 520)
c = login("coordinator"); v = login("volunteer1"); d = login("donor1")
shot("02_dashboard", c.get(f"{BASE}/dashboard.php").text, 820)
shot("03_donors", c.get(f"{BASE}/donors.php").text, 900)
t = tok(v)
shot("04_record_validation_error", v.post(f"{BASE}/donation_form.php", data={"csrf": t, "donor_id": 4, "category_id": 1, "quantity": "0", "donation_date": TODAY, "notes": ""}).text, 820)
shot("05_donations_filtered", c.get(f"{BASE}/donations.php?status=VERIFIED&sort=quantity&dir=desc").text, 700)
shot("06_donation_detail", c.get(f"{BASE}/donation_view.php?id=10").text, 900)
t = tok(c)
r = c.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 10, "action": "distribute", "recipient_name": "Test", "recipient_area": "Lahug", "quantity": "999", "distribution_date": TODAY, "remarks": ""})
shot("07_over_distribution_rejected", r.text, 900)
r = c.post(f"{BASE}/donation_action.php", data={"csrf": t, "donation_id": 10, "action": "distribute", "recipient_name": "Barangay Guadalupe Relief Desk", "recipient_area": "Guadalupe", "quantity": "50", "distribution_date": TODAY, "remarks": "Final release"})
shot("08_distribution_saved", r.text, 900)
shot("09_category_totals", c.get(f"{BASE}/report_totals.php?from=2026-10-01&to={TODAY}").text, 640)
shot("10_categories", c.get(f"{BASE}/categories.php").text, 640)
shot("11_users", c.get(f"{BASE}/users.php").text, 640)
shot("12_access_denied_403", v.get(f"{BASE}/users.php").text, 420)
shot("13_donor_my_donations", d.get(f"{BASE}/my_donations.php").text, 420)
shot("14_donor_form", c.get(f"{BASE}/donor_form.php").text, 700)
