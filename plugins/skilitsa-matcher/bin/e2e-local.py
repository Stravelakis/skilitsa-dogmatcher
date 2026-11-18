import io
import json
import sys
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")

BASE = "http://skilitsa.local"
API = BASE + "/wp-json/skilitsa/v1"


def get(url):
    with urllib.request.urlopen(url, timeout=120) as r:
        return r.status, r.headers, r.read().decode("utf-8")


def post(url, payload):
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url, data=data, headers={"Content-Type": "application/json"}
    )
    with urllib.request.urlopen(req, timeout=180) as r:
        return r.status, json.loads(r.read().decode("utf-8"))


print("=== 1. QUESTIONS ===")
status, _, body = get(API + "/match/questions")
q = json.loads(body)
print("  HTTP %s | questions=%d | flow=%s" % (status, len(q["questions"]), q["flowMode"]))
print("  terms: %s" % q["terms"]["label"][:70])
first = q["questions"][0]
print("  Q1: %s" % first["label"][:70])
print("      low=%s" % first["labelLow"][:45])
print("      high=%s" % first["labelHigh"][:45])
print("      helper=%s" % (first["helper"] or "(none)")[:60])

print("\n=== 2. SCORE (apartment, novice, quiet) ===")
answers = {
    "Q01": 1, "Q02": 1, "Q03": 1, "Q05": 5, "Q06": 1,
    "Q07": 1, "Q13": 1, "Q19": 2, "Q20": 1, "Q23": 1,
}
status, payload = post(
    API + "/match/score",
    {"answers": answers, "dealbreakers": [], "ignored": [], "termsAccepted": True},
)
result = payload["result"]
print("  HTTP %s" % status)
print("  token: %s" % payload["token"])
print("  url  : %s" % payload["resultUrl"])
print("  evidence=%s closeCall=%s" % (result["hasEnoughEvidence"], result["isCloseCall"]))
for i, card in enumerate(result["podium"], 1):
    print("  %d. %-30s %3d%%  img=%s link=%s"
          % (i, card["name"][:30], card["score"],
             "y" if card["imageUrl"] else "n",
             "y" if card["permalink"] else "n"))
    for line in card["aligned"]:
        print("       + %s: %s" % (line["label"], line["body"][:55]))
    for line in card["pitfalls"]:
        print("       ! %s: %s" % (line["label"], line["body"][:55]))
print("  runnersUp: %d | excluded: %d" % (len(result["runnersUp"]), len(result["excluded"])))
print("  disclaimer: %s" % result["disclaimerTitle"][:50])

token = payload["token"]

print("\n=== 3. DEALBREAKER EXCLUSION ===")
status, payload2 = post(
    API + "/match/score",
    {"answers": answers, "dealbreakers": ["Q01"], "ignored": [], "termsAccepted": True},
)
r2 = payload2["result"]
print("  podium=%d excluded=%d blocking=%s"
      % (len(r2["podium"]), len(r2["excluded"]), r2["blockingLabel"]))
if r2["excluded"]:
    print("  first excluded: %s (conflicts: %s)"
          % (r2["excluded"][0]["name"][:30], ", ".join(r2["excluded"][0]["conflicts"])))

print("\n=== 4. STORED RESULT BY TOKEN ===")
status, _, body = get(API + "/match/" + token)
stored = json.loads(body)["result"]
print("  HTTP %s | podium=%d" % (status, len(stored["podium"])))
same = [c["name"] for c in stored["podium"]] == [c["name"] for c in result["podium"]]
print("  identical to the original: %s" % ("YES" if same else "NO"))

print("\n=== 5. BAD TOKEN ===")
try:
    get(API + "/match/aaaaaaaaaaaa")
    print("  unexpectedly succeeded")
except urllib.error.HTTPError as e:
    print("  HTTP %s (expected 404)" % e.code)

print("\n=== 6. RESULT PAGE ===")
status, headers, html = get(BASE + "/match/" + token + "/")
print("  HTTP %s" % status)
print("  Cache-Control : %s" % headers.get("Cache-Control"))
print("  X-Skilitsa    : %s" % headers.get("X-Skilitsa-No-Cache"))
print("  robots noindex: %s" % ("noindex" in html.lower()))
print("  mounts client : %s" % ("data-skilitsa-matcher-result" in html))
print("  page bytes    : %d" % len(html))

leaked = [k for k in ("energy_outdoor", "handler_experience_needed", "prey_drive") if k in html]
print("  breed matrix leaked into HTML: %s" % (", ".join(leaked) if leaked else "no"))

print("\n=== 7. INVALID RESULT PAGE ===")
try:
    get(BASE + "/match/zzzzzzzzzzzz/")
    print("  unexpectedly 200")
except urllib.error.HTTPError as e:
    print("  HTTP %s (expected 404)" % e.code)
