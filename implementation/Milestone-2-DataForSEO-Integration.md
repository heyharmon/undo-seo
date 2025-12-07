__MILESTONE 2 OF 5__

DataForSEO Integration

*Estimated effort: 2\-3 days*

# Overview

Build the backend service layer that communicates with the DataForSEO API\. This includes keyword expansion \(related keywords\), fetching search volume and difficulty metrics, and caching results to minimize API costs\. By the end of this milestone, the app can fetch and store keyword data\.

# Prerequisites

- Milestone 2 complete \(projects working\)
- DataForSEO account with API credentials \(login and password\)

# Deliverables

1. DataForSEO service class
2. Keyword expansion method \(seed → related keywords\)
3. Caching layer with 30\-day expiration
4. API endpoint for triggering keyword expansion
5. Error handling and logging

# Technical Requirements

## 1\. Environment Configuration

Add to \.env:

- DATAFORSEO\_LOGIN=your\_login
- DATAFORSEO\_PASSWORD=your\_password
- KEYWORD\_CACHE\_DAYS=30

## 2\. DataForSEO Service Class

Create App\\Services\\DataForSeoService with the following responsibilities:

__API Authentication__

- DataForSEO uses HTTP Basic Auth with login:password
- Base URL: https://api\.dataforseo\.com/v3
- Use Laravel HTTP client \(Http facade\)

__Keyword Expansion Method__

Use the Keywords Data API → Google → Keyword Suggestions endpoint\.

- Endpoint: POST /keywords\_data/google/keyword\_suggestions/live
- Input: seed keyword, location\_code \(2840 for US\), language\_code \(en\)
- Returns: array of related keywords with search volume
- Limit results to ~100 keywords per expansion \(configurable\)

__Keyword Difficulty Method__

Use the Keywords Data API → Google → Keyword Difficulty endpoint for difficulty scores\.

- Endpoint: POST /keywords\_data/google/keyword\_difficulty/live
- Input: array of keywords \(batch up to 1000\)
- Returns: difficulty score 0\-100 per keyword

__Note: __You may need to make two API calls — one for suggestions \(which includes volume\) and one for difficulty scores\. Alternatively, explore if the Search Volume endpoint returns both\. Check DataForSEO docs for the most efficient approach\.

## 3\. Caching Strategy

Cache keyword data in the keywords table to avoid redundant API calls:

1. Before any API call, check if keyword exists in keywords table
2. If exists AND fetched\_at is within KEYWORD\_CACHE\_DAYS, return cached data
3. If stale or doesn't exist, call API and upsert the keyword record
4. Always update fetched\_at when fresh data is retrieved

This is a global cache — if User A searches for "dog food" and User B searches the same term 5 days later, User B gets cached results without an API call\.

## 4\. Difficulty Labels

Add a method or accessor to the Keyword model that returns the difficulty label:

__Score Range__

__Label__

__Color Suggestion__

0 – 29

Easy

Green

30 – 59

Doable

Yellow/Amber

60 – 100

Hard

Red

## 5\. API Endpoint

Create a route for the frontend to trigger keyword expansion:

__Method__

__URI__

__Purpose__

POST

/api/keywords/expand

Expand a seed keyword

__Request body:__

- keyword \(string, required\) — the seed keyword
- project\_id \(integer, required\) — for context, though not strictly needed yet

__Response:__

- Array of keyword objects: keyword, search\_volume, difficulty, difficulty\_label
- Return cached data where available, fresh data where not

## 6\. Error Handling

- Log all API errors with context \(endpoint, request payload, response\)
- Return user\-friendly error messages \("Unable to fetch keywords\. Please try again\."\)
- Handle rate limits gracefully \(DataForSEO has per\-minute limits\)
- Handle network timeouts \(set reasonable timeout, e\.g\., 30 seconds\)

# Acceptance Criteria

The milestone is complete when:

1. POST /api/keywords/expand with a seed keyword returns related keywords
2. Each returned keyword includes: keyword text, search\_volume, difficulty score, difficulty\_label
3. Calling the same seed keyword twice returns cached results \(verify via logs\)
4. Keywords are stored in the keywords table with fetched\_at timestamp
5. difficulty\_label returns "Easy", "Doable", or "Hard" based on thresholds
6. API errors are logged and return appropriate error responses
7. Endpoint is protected \(requires authentication\)

# Testing Tips

- Use Postman or curl to test the endpoint directly
- Test with a simple seed like "coffee" or "running shoes"
- Check the database to confirm keywords are cached
- Test cache by calling same keyword again — should be faster with no API log
- Test with invalid API credentials to verify error handling

# Notes

- DataForSEO charges per task/request — be mindful during testing
- Consider adding a config value for max keywords returned per expansion
- The frontend will consume this API in Milestone 4 — ensure response format is clean
- If DataForSEO provides both volume and difficulty in one call, use that to reduce costs

