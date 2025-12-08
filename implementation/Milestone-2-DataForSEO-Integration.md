__MILESTONE 2 OF 4__

DataForSEO Integration

*Estimated effort: 2\-3 days*

# Overview

Build the backend service layer that communicates with the DataForSEO API\. This includes fetching related keywords, search volume, and difficulty metrics\. By the end of this milestone, you have a working service class that can retrieve keyword data from DataForSEO\.

# Prerequisites

- Milestone 1 complete \(projects working\)
- DataForSEO account with API credentials \(login and password\)

# Deliverables

1. DataForSEO service class
2. Keyword expansion method \(seed → related keywords\)
3. Keyword difficulty labels \(Easy/Doable/Hard\)
4. Error handling and logging

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

__Related Keywords Method \(Primary\)__

Use the DataForSEO Labs → Google → Related Keywords endpoint\.

- Endpoint: POST /v3/dataforseo\_labs/google/related\_keywords/live
- Input: seed keyword, location\_code \(2840 for US\), language\_code \(en\)
- Returns: semantically related keywords with search volume and connection strength
- Connection strength data is useful for clustering in Milestone 3

__Keyword Suggestions Method \(Secondary\)__

Use the DataForSEO Labs → Google → Keyword Suggestions endpoint\.

- Endpoint: POST /v3/dataforseo\_labs/google/keyword\_suggestions/live
- Input: seed keyword, location\_code, language\_code
- Returns: autocomplete\-style suggestions for long\-tail variations
- Supplements the related keywords to catch variations

__Keyword Difficulty__

Check if the related keywords response includes difficulty scores\. If not, use the Keyword Difficulty endpoint:

- Endpoint: POST /keywords\_data/google/keyword\_difficulty/live
- Input: array of keywords \(batch up to 1000\)
- Returns: difficulty score 0\-100 per keyword

__Note: __Explore the DataForSEO docs to find the most efficient approach\. Ideally, use endpoints that return volume and difficulty together to reduce API costs\.

## 3\. Service Layer Focus

This milestone builds the DataForSEO service layer\. The service should:

1. Accept a seed keyword and return related keywords with metrics
2. Handle API authentication and error cases
3. Return data in a clean format for the caller to use

__Note:__ Milestone 3 defines how keywords are stored \(per\-project with parent/child hierarchy\)\. This milestone focuses on building a reliable service that fetches data from DataForSEO\.

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

## 5\. Error Handling

- Log all API errors with context \(endpoint, request payload, response\)
- Return user\-friendly error messages \("Unable to fetch keywords\. Please try again\."\)
- Handle rate limits gracefully \(DataForSEO has per\-minute limits\)
- Handle network timeouts \(set reasonable timeout, e\.g\., 30 seconds\)

# Acceptance Criteria

The milestone is complete when:

1. DataForSeoService can fetch related keywords for a seed keyword
2. Each returned keyword includes: keyword text, search\_volume, difficulty score
3. difficulty\_label returns "Easy", "Doable", or "Hard" based on thresholds
4. API errors are logged and handled gracefully
5. Service is testable via a simple controller or artisan command

# Testing Tips

- Create a simple artisan command to test the service directly
- Test with a simple seed like "coffee" or "running shoes"
- Test with invalid API credentials to verify error handling
- Check logs to confirm API responses are being captured

# Notes

- DataForSEO charges per task/request — be mindful during testing
- Consider adding a config value for max keywords returned per expansion
- Milestone 3 will use this service to generate and store topical maps
- If DataForSEO provides both volume and difficulty in one call, use that to reduce costs

