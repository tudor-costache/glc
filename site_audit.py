#!/usr/bin/env python3
"""Great Lake Cleaners — live site audit.

Two halves:

  Passive (default)   Read-only requests, indistinguishable from a browser visit.
                      Header hygiene, WordPress exposure points, username
                      enumeration, REST surface, cache config, page health.

  Active (--post)     One real submission per public form. These send real email
                      to info@ and create a real pending post, so they are OFF by
                      default and every payload is loudly marked GLC-AUDIT-TEST
                      with the run timestamp, so it is obvious what to delete.

The active half is the part worth having: it is the only way to confirm the
input-hardening release actually accepts a genuine photo and rejects a spoofed
one, which is exactly the pair a passive scan cannot tell apart.

Usage:
    python site_audit.py                       # passive only
    python site_audit.py --post                # passive + one POST per form
    python site_audit.py --post --only report  # just one surface
    python site_audit.py --base http://localhost:8080

Exit code is 1 if any FAIL was recorded, else 0 — so it can gate a deploy.
"""

import argparse
import io
import json
import mimetypes
import os
import re
import ssl
import struct
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zlib

DEFAULT_BASE = "https://greatlakecleaners.ca"
UA = "glc-site-audit/1.0 (+maintenance check, contact info@greatlakecleaners.ca)"
TIMEOUT = 30

# ── tiny result recorder ─────────────────────────────────────────────────────

PASS, WARN, FAIL, INFO = "PASS", "WARN", "FAIL", "INFO"
_MARK = {PASS: "  ok  ", WARN: " warn ", FAIL: " FAIL ", INFO: " info "}
results = []


def record(status, label, detail=""):
    results.append((status, label, detail))
    line = "[%s] %s" % (_MARK[status], label)
    if detail:
        line += "\n           %s" % detail.replace("\n", "\n           ")
    print(line)


def section(title):
    print("\n" + title)
    print("-" * len(title))


# ── HTTP ─────────────────────────────────────────────────────────────────────

_cookies = {}


def request(url, method="GET", data=None, headers=None, redirect=True):
    """Return (status, headers_dict_lowercased, body_bytes, final_url)."""
    hdrs = {"User-Agent": UA}
    if headers:
        hdrs.update(headers)
    if _cookies:
        hdrs["Cookie"] = "; ".join("%s=%s" % kv for kv in _cookies.items())

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **kw):
            return None

    handlers = [urllib.request.HTTPSHandler(context=ssl.create_default_context())]
    if not redirect:
        handlers.append(NoRedirect)
    opener = urllib.request.build_opener(*handlers)

    req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
    try:
        r = opener.open(req, timeout=TIMEOUT)
        status, raw, body, final = r.status, r.headers, r.read(), r.url
    except urllib.error.HTTPError as e:
        status, raw, body, final = e.code, e.headers, e.read(), url
    except Exception as e:  # DNS, TLS, timeout
        return 0, {}, str(e).encode(), url

    # Keep every value for a header, not just the last — duplicate headers are
    # precisely what this script exists to notice.
    hd = {}
    for k, v in raw.items():
        hd.setdefault(k.lower(), []).append(v.strip())

    for c in raw.get_all("Set-Cookie", []) or []:
        nv = c.split(";", 1)[0]
        if "=" in nv:
            k, v = nv.split("=", 1)
            _cookies[k.strip()] = v.strip()

    return status, hd, body, final


def status_of(path, base, **kw):
    st, _, _, _ = request(urllib.parse.urljoin(base, path), **kw)
    return st


# ── passive checks ───────────────────────────────────────────────────────────

SECURITY_HEADERS = [
    "strict-transport-security",
    "content-security-policy",
    "x-content-type-options",
    "x-frame-options",
    "referrer-policy",
    "permissions-policy",
]


def check_headers(base):
    section("Response headers")
    st, hd, _, _ = request(base)
    if st != 200:
        record(FAIL, "Homepage reachable", "got HTTP %s" % st)
        return
    record(PASS, "Homepage reachable (HTTP 200)")

    for h in SECURITY_HEADERS:
        vals = hd.get(h)
        if not vals:
            record(WARN, "Missing %s" % h)
        elif len(vals) > 1:
            uniq = set(vals)
            record(
                FAIL,
                "Duplicate %s (%d copies)" % (h, len(vals)),
                "values: %s" % " || ".join(sorted(uniq))
                + ("\nIdentical copies — noise." if len(uniq) == 1
                   else "\nCONFLICTING values; browsers merge these unpredictably."),
            )
        else:
            record(PASS, "%s" % h, vals[0][:150])

    # geolocation must survive: the submit + report forms use navigator.geolocation
    pp = " , ".join(hd.get("permissions-policy", []))
    if pp:
        if "geolocation=(self)" in pp.replace(" ", "") or "geolocation=(self)" in pp:
            record(PASS, "Permissions-Policy permits geolocation for self",
                   "the 'Use my location' buttons depend on this")
        elif "geolocation" in pp:
            record(FAIL, "Permissions-Policy restricts geolocation",
                   "the 'Use my location' buttons on /submit-cleanup/ and "
                   "/report-issue/ will silently fail: " + pp[:120])
        else:
            record(INFO, "Permissions-Policy does not mention geolocation",
                   "browser default for geolocation is 'self', so the buttons work")


def check_tls(base):
    section("Transport")
    host = urllib.parse.urlsplit(base).netloc
    st, hd, _, _ = request("http://%s/" % host, redirect=False)
    loc = (hd.get("location") or [""])[0]
    if st in (301, 308) and loc.startswith("https://"):
        record(PASS, "HTTP redirects to HTTPS (%s)" % st)
    else:
        record(FAIL, "No HTTP->HTTPS redirect", "status %s, location %r" % (st, loc))

    hsts = (hd.get("strict-transport-security") or [""])[0]
    st2, hd2, _, _ = request(base)
    hsts = (hd2.get("strict-transport-security") or [""])[0]
    if "max-age=" in hsts:
        age = int(re.search(r"max-age=(\d+)", hsts).group(1))
        ok = age >= 15552000  # 180 days, the submission threshold for preload
        record(PASS if ok else WARN, "HSTS max-age=%d%s" % (
            age, ", includeSubDomains" if "includesubdomains" in hsts.lower() else ""))
    else:
        record(WARN, "No HSTS header")


EXPOSURE = [
    ("readme.html", "WordPress readme (discloses core version)", (403, 404)),
    ("license.txt", "WordPress license", (403, 404)),
    ("xmlrpc.php", "XML-RPC endpoint", (403, 404, 405)),
    ("wp-config.php.bak", "config backup", (403, 404)),
    ("wp-config.php.save", "config backup", (403, 404)),
    ("wp-config.php~", "config backup", (403, 404)),
    (".git/config", "git metadata", (403, 404)),
    (".env", "environment file", (403, 404)),
    ("debug.log", "debug log", (403, 404)),
    ("wp-content/debug.log", "debug log", (403, 404)),
    ("error_log", "PHP error log", (403, 404)),
    ("wp-content/uploads/", "uploads directory listing", (403, 404)),
    ("wp-includes/", "wp-includes listing", (403, 404)),
]


def check_exposure(base):
    section("Exposed files and directories")
    for path, what, good in EXPOSURE:
        st = status_of(path, base)
        if st in good:
            record(PASS, "%s not served (%s)" % (path, st))
        elif st == 200:
            record(WARN, "%s is readable" % path, what)
        else:
            record(INFO, "%s -> HTTP %s" % (path, st))

    # A directory that 200s may still be a harmless blank index.
    st, _, body, _ = request(urllib.parse.urljoin(base, "wp-content/plugins/"))
    if st == 200 and b"Index of" in body:
        record(FAIL, "Plugin directory listing is enabled",
               "reveals every installed plugin and its version")


def check_user_enumeration(base):
    section("Username enumeration")
    leaked = set()

    for path in ("wp-json/wp/v2/users", "?rest_route=/wp/v2/users"):
        st, _, body, _ = request(urllib.parse.urljoin(base, path))
        if st == 200:
            try:
                users = json.loads(body)
                slugs = [u.get("slug") for u in users if isinstance(u, dict)]
                leaked.update(s for s in slugs if s)
                record(FAIL, "/%s exposes %d account(s)" % (path, len(users)),
                       "login slugs: %s" % ", ".join(sorted(x for x in slugs if x)))
            except Exception:
                record(WARN, "/%s returned 200 with unparsed body" % path)
        else:
            record(PASS, "/%s closed (HTTP %s)" % (path, st))

    st, hd, _, _ = request(urllib.parse.urljoin(base, "?author=1"), redirect=False)
    loc = (hd.get("location") or [""])[0]
    m = re.search(r"/author/([^/]+)/?", loc)
    if m:
        leaked.add(m.group(1))
        record(FAIL, "?author=1 redirects to /author/%s/" % m.group(1),
               "confirms the login slug of user ID 1")
    elif st in (404, 403):
        record(PASS, "Author archive enumeration blocked (HTTP %s)" % st)
    else:
        record(INFO, "?author=1 -> HTTP %s %s" % (st, loc))

    if leaked:
        record(WARN, "wp-login.php is public and %d username(s) are known"
               % len(leaked), "credential stuffing has half of what it needs")


def check_rest(base):
    section("REST API surface")
    for path, expect_open, note in [
        ("wp-json/wp/v2/settings", False, "site settings must require auth"),
        ("wp-json/wp/v2/glc_submission", False, "submissions hold submitter email/phone"),
    ]:
        st = status_of(path, base)
        closed = st in (401, 403, 404)
        if closed != (not expect_open):
            record(FAIL, "/%s -> HTTP %s" % (path, st), note)
        else:
            record(PASS, "/%s -> HTTP %s" % (path, st), note)

    # Media is public by design; what matters is that nothing behind an
    # unapproved submission is reachable through it.
    st, hd, body, _ = request(urllib.parse.urljoin(base, "wp-json/wp/v2/media?per_page=100"))
    if st != 200:
        record(INFO, "Media endpoint -> HTTP %s" % st)
        return
    total = int((hd.get("x-wp-total") or ["0"])[0] or 0)
    try:
        items = json.loads(body)
    except Exception:
        record(WARN, "Media endpoint returned unparsable JSON")
        return
    pages = int((hd.get("x-wp-totalpages") or ["1"])[0] or 1)
    for p in range(2, min(pages, 5) + 1):
        _, _, b2, _ = request(urllib.parse.urljoin(
            base, "wp-json/wp/v2/media?per_page=100&page=%d" % p))
        try:
            items += json.loads(b2)
        except Exception:
            break

    parents = sorted({m.get("post") for m in items if m.get("post")})
    record(INFO, "Media endpoint lists %d attachment(s), %d distinct parent post(s)"
           % (total, len(parents)))

    hidden = []
    for pid in parents:
        st2, hd2, _, _ = request(urllib.parse.urljoin(base, "?p=%d" % pid),
                                 redirect=False)
        if st2 not in (200, 301, 302):
            hidden.append((pid, st2))
    if hidden:
        record(FAIL, "%d attachment parent(s) are not publicly viewable" % len(hidden),
               "photos from unapproved submissions may be enumerable: %s"
               % ", ".join("%s(%s)" % h for h in hidden[:10]))
    else:
        record(PASS, "Every attachment parent is a published post",
               "no unapproved-submission photos exposed via REST")


def check_pages(base):
    section("Page health")
    for slug in ["", "cleanups", "stats", "photos", "videos", "submit-cleanup",
                 "report-issue", "join-crew", "see-us-in-action", "events"]:
        path = "%s/" % slug if slug else ""
        st = status_of(path, base)
        if st == 200:
            record(PASS, "/%s (200)" % slug)
        elif slug == "events" and st == 404:
            record(INFO, "/events/ -> 404", "expected until the events rollout ships")
        else:
            record(FAIL, "/%s -> HTTP %s" % (slug, st))


def check_assets(base):
    section("Static asset config")
    url = urllib.parse.urljoin(
        base, "wp-content/plugins/great-lake-cleaners/assets/corridors.geojson")
    st, hd, body, _ = request(url, headers={"Accept-Encoding": "gzip"})
    if st != 200:
        record(WARN, "corridors.geojson -> HTTP %s" % st)
        return
    enc = (hd.get("content-encoding") or [""])[0]
    cc = (hd.get("cache-control") or [""])[0]
    ct = (hd.get("content-type") or [""])[0]
    record(PASS if "gzip" in enc else WARN,
           "corridors.geojson content-encoding: %s" % (enc or "none"),
           "1.3MB uncompressed — gzip matters here")
    record(PASS if "max-age" in cc else WARN,
           "corridors.geojson cache-control: %s" % (cc or "none"))
    record(INFO, "corridors.geojson content-type: %s" % ct)


def check_html_encoding(base):
    """Catch mojibake and stray escape sequences in rendered output.

    Born from a real bug: '\\u2026' written inside a PHP single-quoted string is
    not an escape, and esc_js()'s stripslashes() then ate the backslash, so a
    button rendered the literal text "Detectingu2026".
    """
    section("Rendered HTML encoding")
    # Mojibake has to be matched on its *encoded* form. A bare 0xE2 is simply the
    # first byte of every U+2000-block character - the em dash and ellipsis this
    # site uses constantly - so testing for it flags every correctly encoded page.
    suspects = [
        (re.compile(rb"\xc3\xa2\xe2\x82\xac"),
         "UTF-8 decoded as Latin-1 then re-encoded (mojibake)"),
        (re.compile(rb"\xc3\x83\xc2"), "double-encoded UTF-8 (mojibake)"),
        (re.compile(rb"&amp;#\d"), "double-encoded HTML entity"),
    ]
    for slug in ["submit-cleanup", "report-issue", "join-crew", "cleanups", "stats"]:
        st, hd, body, _ = request(urllib.parse.urljoin(base, "%s/" % slug))
        if st != 200:
            continue
        hits = []
        for pat, why in suspects:
            found = pat.search(body)
            if found:
                hits.append("%s: %r" % (why, found.group(0)))

        # The high-signal check, and the reason this function exists at all: a
        # word running straight into uXXXX means a backslash was eaten before the
        # browser saw it. A unicode escape written inside a PHP *single-quoted*
        # string is not an escape sequence, and esc_js() then calls stripslashes()
        # on it, so the button rendered the literal text "Detectingu2026".
        # Scan the whole body, <script> included - that is where it landed.
        m = re.search(rb"[A-Za-z]u[0-9a-fA-F]{4}\b", body)
        if m:
            hits.append("bare uXXXX after a word (a backslash was eaten): %r"
                        % m.group(0))
        if hits:
            record(FAIL, "/%s/ encoding problem" % slug, "\n".join(hits))
        else:
            record(PASS, "/%s/ clean" % slug)


# ── active checks ────────────────────────────────────────────────────────────

def png_bytes(w=8, h=8):
    """A real, valid PNG — must survive glc_validate_image_upload()."""
    def chunk(tag, data):
        return (struct.pack(">I", len(data)) + tag + data
                + struct.pack(">I", zlib.crc32(tag + data) & 0xFFFFFFFF))
    raw = b"".join(b"\x00" + b"\x3c\x8f\xc4" * w for _ in range(h))
    return (b"\x89PNG\r\n\x1a\n"
            + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0))
            + chunk(b"IDAT", zlib.compress(raw))
            + chunk(b"IEND", b""))


def multipart(fields, files):
    """Build a multipart/form-data body.

    `files` entries are (field, filename, content_type, bytes) — content_type is
    attacker-controlled in the real world, which is the whole point of the
    spoofed-upload test below.
    """
    b = uuid.uuid4().hex
    out = io.BytesIO()
    for k, v in fields.items():
        out.write(("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n"
                   % (b, k, v)).encode("utf-8"))
    for field, fname, ctype, blob in files:
        out.write(("--%s\r\nContent-Disposition: form-data; name=\"%s\"; "
                   "filename=\"%s\"\r\nContent-Type: %s\r\n\r\n"
                   % (b, field, fname, ctype)).encode("utf-8"))
        out.write(blob)
        out.write(b"\r\n")
    out.write(("--%s--\r\n" % b).encode("utf-8"))
    return out.getvalue(), "multipart/form-data; boundary=%s" % b


def get_nonce(base, path, field):
    st, _, body, _ = request(urllib.parse.urljoin(base, path))
    if st != 200:
        return None, "page returned HTTP %s" % st
    m = re.search(
        (r'name=["\']%s["\'][^>]*value=["\']([a-f0-9]+)["\']' % re.escape(field)).encode(),
        body)
    if not m:
        m = re.search(
            (r'value=["\']([a-f0-9]{8,12})["\'][^>]*name=["\']%s["\']'
             % re.escape(field)).encode(), body)
    if not m:
        return None, "could not find the %s field" % field
    return m.group(1).decode(), None


def get_data_nonce(base, path, attr="data-nonce"):
    st, _, body, _ = request(urllib.parse.urljoin(base, path))
    if st != 200:
        return None, "page returned HTTP %s" % st
    m = re.search((r'%s=["\']([a-f0-9]+)["\']' % re.escape(attr)).encode(), body)
    return (m.group(1).decode(), None) if m else (None, "no %s found" % attr)


def post_submission(base, stamp):
    label = "Submit a Cleanup"
    nonce, err = get_nonce(base, "submit-cleanup/", "glc_submit_nonce")
    if err:
        record(FAIL, "%s: nonce" % label, err)
        return
    fields = {
        "glc_submit_nonce": nonce,
        "_wp_http_referer": "/submit-cleanup/",
        "glc_submit_cleanup": "1",
        "glc_submitter_name": "GLC-AUDIT-TEST %s" % stamp,
        "glc_email": "audit-test@example.com",
        "glc_cleanup_date": time.strftime("%Y-%m-%d"),
        "glc_waterway": "GLC-AUDIT-TEST %s (delete me)" % stamp,
        "glc_site_name": "GLC-AUDIT-TEST",
        "glc_bags": "1",
        "glc_weight_kg": "1.5",
        "glc_volunteers": "1",
        "glc_duration_min": "30",
        "glc_notable_finds": "Automated audit submission - safe to delete.",
    }
    body, ctype = multipart(
        fields, [("glc_photos[]", "audit.png", "image/png", png_bytes())])
    st, _, resp, _ = request(urllib.parse.urljoin(base, "submit-cleanup/"),
                             method="POST", data=body,
                             headers={"Content-Type": ctype})
    ok = st == 200 and b"glc-submit-success" in resp
    record(PASS if ok else FAIL, "%s accepted a valid PNG" % label,
           "HTTP %s%s" % (st, "" if ok else " — success block not found in response"))
    if ok:
        m = re.search(rb"You submitted:([^<]*)", resp)
        if m:
            record(INFO, "%s receipt" % label, m.group(1).decode("utf-8", "replace").strip())
        record(WARN, "A pending submission was created",
               "WP Admin -> Submissions -> trash the one titled GLC-AUDIT-TEST %s" % stamp)


def post_submission_spoofed(base, stamp):
    """The regression test for the upload hardening.

    A non-image with an image Content-Type — exactly what the old
    in_array($_FILES['type']) check waved through. The submission itself should
    still succeed; the *photo* must be dropped.
    """
    label = "Submit a Cleanup (spoofed upload)"
    nonce, err = get_nonce(base, "submit-cleanup/", "glc_submit_nonce")
    if err:
        record(FAIL, "%s: nonce" % label, err)
        return
    payload = b"%PDF-1.4\n% not an image at all\n1 0 obj<</Type/Catalog>>endobj\ntrailer\n%%EOF\n"
    fields = {
        "glc_submit_nonce": nonce,
        "_wp_http_referer": "/submit-cleanup/",
        "glc_submit_cleanup": "1",
        "glc_submitter_name": "GLC-AUDIT-TEST %s spoof" % stamp,
        "glc_cleanup_date": time.strftime("%Y-%m-%d"),
        "glc_waterway": "GLC-AUDIT-TEST %s spoof (delete me)" % stamp,
        "glc_bags": "1",
        "glc_volunteers": "1",
    }
    body, ctype = multipart(
        fields, [("glc_photos[]", "payload.jpg", "image/jpeg", payload)])
    st, _, resp, _ = request(urllib.parse.urljoin(base, "submit-cleanup/"),
                             method="POST", data=body,
                             headers={"Content-Type": ctype})
    if st != 200 or b"glc-submit-success" not in resp:
        record(WARN, "%s did not complete" % label, "HTTP %s" % st)
        return
    record(PASS, "%s: form completed" % label)
    # Confirm the file never landed in the media library.
    time.sleep(2)
    _, _, mbody, _ = request(urllib.parse.urljoin(
        base, "wp-json/wp/v2/media?per_page=20&orderby=date&order=desc"))
    try:
        recent = json.loads(mbody)
    except Exception:
        recent = []
    bad = [m for m in recent
           if "payload" in (m.get("source_url") or "")
           or (m.get("mime_type") or "") == "application/pdf"]
    if bad:
        record(FAIL, "Spoofed upload REACHED the media library",
               "the Content-Type check is being trusted again: %s"
               % ", ".join(str(m.get("source_url")) for m in bad[:3]))
    else:
        record(PASS, "Spoofed upload was rejected",
               "non-image with Content-Type: image/jpeg did not reach the media library")
    record(WARN, "A second pending submission was created",
           "trash 'GLC-AUDIT-TEST %s spoof' as well" % stamp)


def post_report(base, stamp):
    label = "Report an Issue"
    nonce, err = get_nonce(base, "report-issue/", "glc_report_nonce")
    if err:
        record(FAIL, "%s: nonce" % label, err)
        return
    fields = {
        "glc_report_nonce": nonce,
        "_wp_http_referer": "/report-issue/",
        "glc_submit_report": "1",
        "glc_reporter_name": "GLC-AUDIT-TEST %s" % stamp,
        "glc_reporter_email": "audit-test@example.com",
        "glc_issue_date": time.strftime("%Y-%m-%d"),
        "glc_issue_waterway": "GLC-AUDIT-TEST",
        "glc_issue_location": "Automated audit - no action needed",
        "glc_issue_description":
            "GLC-AUDIT-TEST %s. Automated check that the report form still "
            "delivers mail and attaches photos. No real issue. Safe to ignore." % stamp,
    }
    body, ctype = multipart(
        fields, [("glc_report_photos[]", "audit.png", "image/png", png_bytes())])
    st, _, resp, _ = request(urllib.parse.urljoin(base, "report-issue/"),
                             method="POST", data=body,
                             headers={"Content-Type": ctype})
    ok = st == 200 and b"glc-report-success" in resp
    record(PASS if ok else FAIL, "%s accepted a valid PNG" % label,
           "HTTP %s%s" % (st, "" if ok else " — success block not found"))
    if ok:
        record(WARN, "An email was sent to info@",
               "subject contains GLC-AUDIT-TEST — safe to delete")


def post_crew(base, stamp):
    label = "Join our Crew"
    nonce, err = get_data_nonce(base, "join-crew/")
    if err:
        record(FAIL, "%s: nonce" % label, err)
        return
    data = urllib.parse.urlencode({
        "action": "glc_crew_signup",
        "nonce": nonce,
        "glc_crew_name": "GLC-AUDIT-TEST %s" % stamp,
        "glc_crew_email": "audit-test@example.com",
    }).encode()
    st, _, resp, _ = request(
        urllib.parse.urljoin(base, "wp-admin/admin-ajax.php"), method="POST",
        data=data, headers={"Content-Type": "application/x-www-form-urlencoded"})
    try:
        j = json.loads(resp)
        ok = bool(j.get("success"))
        msg = j.get("data")
    except Exception:
        ok, msg = False, resp[:120].decode("utf-8", "replace")
    record(PASS if ok else FAIL, "%s AJAX" % label, "HTTP %s — %s" % (st, msg))
    if ok:
        record(WARN, "An email was sent to info@", "GLC-AUDIT-TEST signup")


def post_rsvp(base, stamp):
    label = "Event RSVP"
    st, _, body, _ = request(urllib.parse.urljoin(base, "events/"))
    if st != 200:
        record(INFO, "%s skipped" % label, "/events/ -> HTTP %s (rollout pending)" % st)
        return
    m = re.search(rb'name="glc_rsvp_event_id"\s+value="(\d+)"', body)
    if not m:
        st2, _, body2, _ = request(urllib.parse.urljoin(base, "events/"))
        links = re.findall(rb'href="([^"]*/events/[^"/]+/)"', body2)
        if not links:
            record(INFO, "%s skipped" % label, "no upcoming event with an RSVP form")
            return
        st3, _, body, _ = request(links[0].decode())
        m = re.search(rb'name="glc_rsvp_event_id"\s+value="(\d+)"', body)
        if not m:
            record(INFO, "%s skipped" % label, "no RSVP form on the first event page")
            return
    event_id = m.group(1).decode()
    nm = re.search(rb'data-nonce="([a-f0-9]+)"', body)
    if not nm:
        record(FAIL, "%s: nonce" % label, "no data-nonce on the RSVP form")
        return
    data = urllib.parse.urlencode({
        "action": "glc_event_rsvp",
        "nonce": nm.group(1).decode(),
        "glc_rsvp_event_id": event_id,
        "glc_rsvp_name": "GLC-AUDIT-TEST %s" % stamp,
        "glc_rsvp_email": "audit-test@example.com",
        "glc_rsvp_party": "1",
    }).encode()
    st, _, resp, _ = request(
        urllib.parse.urljoin(base, "wp-admin/admin-ajax.php"), method="POST",
        data=data, headers={"Content-Type": "application/x-www-form-urlencoded"})
    try:
        j = json.loads(resp)
        ok, msg = bool(j.get("success")), j.get("data")
    except Exception:
        ok, msg = False, resp[:120].decode("utf-8", "replace")
    record(PASS if ok else FAIL, "%s AJAX (event %s)" % (label, event_id),
           "HTTP %s — %s" % (st, msg))
    if ok:
        record(WARN, "RSVP counters were incremented on event %s" % event_id,
               "adjust rsvp_count / rsvp_parties in the meta box if it matters")


def check_honeypot(base):
    """The honeypot should absorb a bot silently — no mail, no post."""
    section("Bot controls")
    nonce, err = get_nonce(base, "report-issue/", "glc_report_nonce")
    if err:
        record(INFO, "Honeypot check skipped", err)
        return
    fields = {
        "glc_report_nonce": nonce,
        "glc_submit_report": "1",
        "glc_url": "http://spam.example.com",   # the honeypot field
        "glc_issue_date": time.strftime("%Y-%m-%d"),
        "glc_issue_waterway": "GLC-AUDIT-HONEYPOT",
        "glc_issue_location": "GLC-AUDIT-HONEYPOT",
        "glc_issue_description": "Honeypot probe - should never be delivered.",
    }
    body, ctype = multipart(fields, [])
    st, _, resp, _ = request(urllib.parse.urljoin(base, "report-issue/"),
                             method="POST", data=body,
                             headers={"Content-Type": ctype})
    if st == 200 and b"glc-report-success" not in resp:
        record(PASS, "Honeypot absorbed the submission",
               "no success state returned, so no mail was sent")
    else:
        record(FAIL, "Honeypot did not stop the submission",
               "HTTP %s and a success state came back" % st)

    # A bad nonce must be refused.
    fields2 = dict(fields, glc_url="", glc_report_nonce="deadbeef")
    body2, ctype2 = multipart(fields2, [])
    st2, _, resp2, _ = request(urllib.parse.urljoin(base, "report-issue/"),
                               method="POST", data=body2,
                               headers={"Content-Type": ctype2})
    if b"Security check failed" in resp2:
        record(PASS, "Invalid nonce rejected")
    elif b"glc-report-success" in resp2:
        record(FAIL, "Invalid nonce was ACCEPTED", "nonce verification is not working")
    else:
        record(INFO, "Invalid nonce: no explicit message (HTTP %s)" % st2)


# ── main ─────────────────────────────────────────────────────────────────────

SURFACES = {
    "submit": post_submission,
    "spoof": post_submission_spoofed,
    "report": post_report,
    "crew": post_crew,
    "rsvp": post_rsvp,
}


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base", default=DEFAULT_BASE, help="site root")
    ap.add_argument("--post", action="store_true",
                    help="also submit one real entry per public form "
                         "(sends mail to info@, creates pending posts)")
    ap.add_argument("--only", choices=sorted(SURFACES), action="append",
                    help="limit --post to these surfaces (repeatable)")
    ap.add_argument("--force", action="store_true",
                    help="run --post again inside the cooldown window")
    args = ap.parse_args()

    # Console encoding: the output contains en dashes and arrows, and a default
    # Windows console is cp1252. Without this they render as '?' at best and
    # raise UnicodeEncodeError at worst.
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8", errors="replace")
        except Exception:
            pass

    base = args.base.rstrip("/") + "/"
    stamp = time.strftime("%Y%m%d-%H%M")

    # --post has real side effects: pending posts in the database and mail in a
    # human's inbox. Paging this script's output through `head`/`sed` re-runs it
    # from the top and fires them all again — which is exactly how the first run
    # of this script produced two of everything. Refuse a repeat inside a short
    # window unless it is explicitly asked for.
    post_skipped = False
    cooldown = 600
    stampfile = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                             ".site_audit_last_post")
    if args.post and not args.force:
        try:
            age = time.time() - os.path.getmtime(stampfile)
        except OSError:
            age = cooldown + 1
        if age < cooldown:
            # Degrade to passive rather than aborting — the read-only checks are
            # still worth running, and this is usually someone paging output.
            print("\nNOTE: skipping the POST phase — the last one was %d seconds "
                  "ago.\n      Each --post run creates real submissions and sends "
                  "real mail.\n      To page this output, redirect to a file "
                  "rather than re-running:\n"
                  "          python site_audit.py --post > audit.txt 2>&1\n"
                  "      Use --force to POST anyway." % age)
            args.post = False
            post_skipped = True

    print("Great Lake Cleaners — site audit")
    print("target: %s" % base)
    print("mode:   %s" % ("passive + POST" if args.post else "passive (read-only)"))

    check_headers(base)
    check_tls(base)
    check_exposure(base)
    check_user_enumeration(base)
    check_rest(base)
    check_pages(base)
    check_assets(base)
    check_html_encoding(base)

    if args.post:
        check_honeypot(base)

        section("Live form submissions  [run tag: GLC-AUDIT-TEST %s]" % stamp)
        chosen = args.only or list(SURFACES)
        for name in ["submit", "spoof", "report", "crew", "rsvp"]:
            if name in chosen:
                SURFACES[name](base, stamp)
                time.sleep(3)   # stay well under the 5-per-10-min per-IP limit
        try:
            io.open(stampfile, "w").write(stamp)
        except OSError:
            pass
    else:
        section("Live form submissions")
        record(INFO, "Skipped",
               "cooldown still active - use --force to POST anyway" if post_skipped
               else "re-run with --post to exercise the forms for real")

    section("Summary")
    counts = {}
    for s, _, _ in results:
        counts[s] = counts.get(s, 0) + 1
    print("  ".join("%s: %d" % (k, counts.get(k, 0))
                    for k in (PASS, WARN, FAIL, INFO)))
    fails = [r for r in results if r[0] == FAIL]
    if fails:
        print("\nFailures:")
        for _, label, detail in fails:
            print("  - %s" % label)
    if args.post:
        print("\nCleanup: trash any post titled 'GLC-AUDIT-TEST %s' in "
              "WP Admin -> Submissions, and delete the matching info@ emails."
              % stamp)
    return 1 if fails else 0


if __name__ == "__main__":
    sys.exit(main())
