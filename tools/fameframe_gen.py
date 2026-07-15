#!/usr/bin/env python3
"""FameFrame card generator.

Finds famous people in a category, grabs a portrait image URL for each,
and produces FameFrame cards (name, image_url, tags). Cards go either to
a JSON file for review (--json) or straight into the FameFrame database
via POST /fameframe_api (--post), skipping anyone already in the database.

Sources:
  movies / tv / celebrity  -> TMDB (needs TMDB_API_KEY from .env or env)
  sports / politics / music -> Wikidata (no key needed)

Examples:
  python3 tools/fameframe_gen.py --category movies --count 25 --json movies.json
  python3 tools/fameframe_gen.py --category sports --count 20 --post http://localhost:8000
"""

import argparse
import json
import os
import sys
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

USER_AGENT = "FameFrameCardGen/1.0 (personal flashcard project)"
TMDB_CATEGORIES = ("movies", "tv", "celebrity")
WIKIDATA_CATEGORIES = ("sports", "politics", "music")
ALL_CATEGORIES = TMDB_CATEGORIES + WIKIDATA_CATEGORIES

# Wikidata occupation QIDs per category, with the sub-tag each one implies.
WIKIDATA_OCCUPATIONS = {
    "sports": {
        "Q3665646": "basketball",
        "Q937857": "soccer",
        "Q14128148": "football",
        "Q10833314": "tennis",
        "Q10871364": "baseball",
        "Q11338576": "boxing",
        "Q11303721": "golf",
        "Q11774891": "hockey",
        "Q10843402": "swimming",
        "Q11513337": "track",
    },
    "politics": {"Q82955": ""},
    # No broad occupations here ("musician", "composer") — Wikidata credits
    # famous polymaths (da Vinci, Nietzsche, Rousseau) with them, polluting
    # the category. Singers/rappers stay clean.
    "music": {
        "Q177220": "singer",
        "Q2252262": "rapper",
        "Q488205": "singer",
    },
}

# People famous for these occupations shouldn't appear in the category, even if
# Wikidata also records the category occupation (e.g. Albert Camus was a goalkeeper,
# George H. W. Bush a baseball player).
WIKIDATA_EXCLUDE = {
    "sports": ["Q82955", "Q36180", "Q4964182", "Q33999", "Q10800557", "Q177220",
               "Q2526255", "Q639669"],  # politician, writer, philosopher, actor(x2), singer, director, musician
    "politics": [],
    "music": ["Q82955", "Q10800557", "Q2526255"],  # politician, film actor, film director
}

TMDB_DEPARTMENT_TAGS = {
    "Acting": "actor",
    "Directing": "director",
    "Production": "producer",
    "Writing": "writer",
}


def http_get_json(url, headers=None, timeout=30):
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, **(headers or {})})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8"))


def http_post_json(url, payload, timeout=30):
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=data,
        headers={"User-Agent": USER_AGENT, "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8"))


def normalize_name(name):
    """Case-, whitespace- and diacritic-insensitive key for duplicate checks."""
    decomposed = unicodedata.normalize("NFKD", name)
    stripped = "".join(c for c in decomposed if not unicodedata.combining(c))
    return " ".join(stripped.casefold().split())


def load_env(repo_root):
    """Read KEY=VALUE pairs from the repo's .env without overriding real env vars."""
    env = {}
    env_file = repo_root / ".env"
    if env_file.exists():
        for line in env_file.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                key, _, value = line.partition("=")
                env[key.strip()] = value.strip().strip("'\"")
    return env


def fetch_existing_names(api_base):
    """Return the set of normalized names already in the FameFrame database."""
    url = api_base.rstrip("/") + "/fameframe_api"
    data = http_get_json(url)
    return {normalize_name(card["name"]) for card in data.get("cards", [])}


def tmdb_get(path, api_key, **params):
    params["api_key"] = api_key
    url = f"https://api.themoviedb.org/3{path}?" + urllib.parse.urlencode(params)
    return http_get_json(url)


def make_card(name, profile_path, tags, seen):
    """Build a card unless the person is a duplicate or has no photo."""
    name = (name or "").strip()
    if not name or not profile_path:
        return None
    key = normalize_name(name)
    if key in seen:
        return None
    seen.add(key)
    return {
        "name": name,
        "image_url": f"https://image.tmdb.org/t/p/w500{profile_path}",
        "tags": tags,
    }


def tmdb_people(category, needed, api_key, seen):
    """Cards for famous screen people from TMDB.

    TMDB's person/popular ranking is too noisy to trust, so fame is derived
    from the work instead: top-billed cast of the most-voted movies/shows of
    all time. For 'celebrity', trending people are kept only if their combined
    filmography has substantial audience votes.
    """
    cards = []
    if category in ("movies", "tv"):
        kind = "movie" if category == "movies" else "tv"
        for page in range(1, 11):
            titles = tmdb_get(f"/discover/{kind}", api_key,
                              sort_by="vote_count.desc", page=page).get("results", [])
            if not titles:
                break
            for title in titles:
                credits_path = (f"/movie/{title['id']}/credits" if kind == "movie"
                                else f"/tv/{title['id']}/aggregate_credits")
                cast = tmdb_get(credits_path, api_key).get("cast", [])[:8]
                for member in cast:
                    card = make_card(member.get("name"), member.get("profile_path"),
                                     f"{category}, actor", seen)
                    if card:
                        cards.append(card)
                        if len(cards) >= needed:
                            return cards
    else:  # celebrity: trending people, kept only if their filmography is widely voted on
        for page in range(1, 21):
            people = tmdb_get("/trending/person/week", api_key, page=page).get("results", [])
            if not people:
                break
            for person in people:
                if not person.get("profile_path"):
                    continue
                credits = tmdb_get(f"/person/{person['id']}/combined_credits", api_key)
                votes = sum(c.get("vote_count", 0)
                            for c in credits.get("cast", []) + credits.get("crew", []))
                if votes < 20000:
                    continue
                sub_tag = TMDB_DEPARTMENT_TAGS.get(person.get("known_for_department", ""), "")
                tags = "celebrity" if not sub_tag else f"celebrity, {sub_tag}"
                card = make_card(person.get("name"), person.get("profile_path"), tags, seen)
                if card:
                    cards.append(card)
                    if len(cards) >= needed:
                        return cards
    return cards


def sparql_rows(url):
    """Run a SPARQL query, retrying with backoff when the endpoint is overloaded."""
    for attempt in range(4):
        try:
            return http_get_json(url, timeout=90)["results"]["bindings"]
        except urllib.error.HTTPError as e:
            if e.code in (429, 502, 503, 504) and attempt < 3:
                wait = 15 * (attempt + 1)
                print(f"  Wikidata endpoint busy (HTTP {e.code}); retrying in {wait}s…")
                time.sleep(wait)
            else:
                raise
    return []


def wikidata_people(category, needed, seen):
    """Yield cards for notable people from Wikidata, ranked by sitelink count."""
    occupations = WIKIDATA_OCCUPATIONS[category]
    values = " ".join(f"wd:{qid}" for qid in occupations)
    exclude = ""
    if WIKIDATA_EXCLUDE[category]:
        bad = " ".join(f"wd:{qid}" for qid in WIKIDATA_EXCLUDE[category])
        exclude = f"FILTER NOT EXISTS {{ VALUES ?bad {{ {bad} }} ?person wdt:P106 ?bad . }}"
    cards = []
    # Start with a high notability floor and relax it if the category runs dry.
    for floor in (70, 40, 20):
        # Query shape matters here: no server-side ORDER BY (sorting a whole
        # occupation set forces a full scan and times out), and the optimizer
        # hint makes Blazegraph stream occupation -> sitelinks -> LIMIT in
        # written order. Anyone above the fame floor qualifies; rank locally.
        query = f"""
        SELECT ?person ?personLabel ?image ?sitelinks ?occ WHERE {{
          hint:Query hint:optimizer "None" .
          {{
            SELECT ?person ?image ?sitelinks ?occ WHERE {{
              hint:Query hint:optimizer "None" .
              VALUES ?occ {{ {values} }}
              ?person wdt:P106 ?occ ;
                      wikibase:sitelinks ?sitelinks .
              FILTER(?sitelinks >= {floor})
              ?person wdt:P18 ?image .
            }}
            LIMIT {max(needed * 6, 200)}
          }}
          {exclude}
          SERVICE wikibase:label {{ bd:serviceParam wikibase:language "en". }}
        }}
        """
        url = "https://query.wikidata.org/sparql?" + urllib.parse.urlencode(
            {"query": query, "format": "json"}
        )
        rows = sparql_rows(url)
        rows.sort(key=lambda r: -int(r["sitelinks"]["value"]))
        for row in rows:
            if len(cards) >= needed:
                return cards
            name = row["personLabel"]["value"].strip()
            # A missing English label comes back as the bare QID; not usable as a card.
            if not name or (name.startswith("Q") and name[1:].isdigit()):
                continue
            key = normalize_name(name)
            if key in seen:
                continue
            seen.add(key)
            image = row["image"]["value"].replace("http://", "https://") + "?width=500"
            occ_qid = row["occ"]["value"].rsplit("/", 1)[-1]
            sub_tag = occupations.get(occ_qid, "")
            tags = category if not sub_tag else f"{category}, {sub_tag}"
            cards.append({"name": name, "image_url": image, "tags": tags})
        if len(cards) >= needed:
            return cards
        time.sleep(1)  # be polite to the SPARQL endpoint between retries
    return cards


def main():
    parser = argparse.ArgumentParser(description="Generate FameFrame cards for famous people.")
    parser.add_argument("--category", required=True, choices=ALL_CATEGORIES)
    parser.add_argument("--count", type=int, default=20, help="how many new cards to produce")
    output = parser.add_mutually_exclusive_group(required=True)
    output.add_argument("--json", metavar="FILE", help="write cards to a JSON file")
    output.add_argument("--post", metavar="URL", help="POST cards to this FameFrame server")
    parser.add_argument(
        "--api",
        metavar="URL",
        default="http://localhost:8000",
        help="server to check for existing names in --json mode (default: %(default)s)",
    )
    args = parser.parse_args()

    repo_root = Path(__file__).resolve().parent.parent
    api_base = args.post if args.post else args.api

    # Duplicate protection: never produce a card for someone already in the database.
    try:
        existing = fetch_existing_names(api_base)
        print(f"Found {len(existing)} existing cards at {api_base}")
    except (urllib.error.URLError, OSError) as e:
        if args.post:
            sys.exit(f"Cannot reach {api_base} to check for duplicates: {e}")
        existing = set()
        print(f"Warning: could not reach {api_base} to check for duplicates ({e}); "
              "the JSON file may contain people already in the database.")

    seen = set(existing)
    if args.category in TMDB_CATEGORIES:
        env = load_env(repo_root)
        api_key = os.environ.get("TMDB_API_KEY") or env.get("TMDB_API_KEY")
        if not api_key:
            sys.exit("TMDB_API_KEY not found in environment or .env")
        cards = tmdb_people(args.category, args.count, api_key, seen)
    else:
        cards = wikidata_people(args.category, args.count, seen)

    if not cards:
        sys.exit("No new people found — everyone matched is already in the database.")

    if args.json:
        Path(args.json).write_text(json.dumps(cards, indent=2, ensure_ascii=False) + "\n")
        print(f"Wrote {len(cards)} new cards to {args.json}")
    else:
        url = api_base.rstrip("/") + "/fameframe_api"
        added = 0
        for card in cards:
            result = http_post_json(url, card)
            if result.get("success"):
                added += 1
                print(f"  added: {card['name']} [{card['tags']}]")
            else:
                print(f"  FAILED: {card['name']}: {result}")
        print(f"Added {added} of {len(cards)} new cards to {api_base}")

    if args.json:
        preview = ", ".join(c["name"] for c in cards[:5])
        print(f"Preview: {preview}{'…' if len(cards) > 5 else ''}")


if __name__ == "__main__":
    main()
