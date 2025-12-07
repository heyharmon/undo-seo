SEO Keyword Research Tool

MVP Implementation Guide

# 1\. Product Overview

## The Problem

Solopreneurs launching new businesses need keyword research to find market opportunities, but existing tools like SEMrush, Ahrefs, and Clicks\.so cost $25\-$200/month for features they'll never use\. The DataForSEO API provides the same underlying data for a fraction of the cost, but requires technical implementation\.

## The Solution

A simple, $5/month keyword research tool that helps solopreneurs find keywords they can actually rank for\. No enterprise features, no complexity — just a clear path to identifying low\-competition keywords for market entry\.

## Positioning Statement

*"The go\-to\-market keyword tool for solopreneurs\."*

## The Aha Moment

User enters a seed keyword and immediately sees which related keywords are Easy, Doable, or Hard to rank for — giving them instant clarity on where to focus their content efforts\.

# 2\. Core User Flow

1. __Sign up and pay $5/month __— Stripe subscription, no free trial
2. __Create a project __— Name it \(e\.g\., "My SaaS Blog" or "Client: Acme Corp"\)
3. __Enter a seed keyword __— The topic they want to rank for
4. __View expanded keywords __— See related keywords with volume, difficulty, and Easy/Doable/Hard labels
5. __Save keywords to topical map __— Build a list of target keywords over time
6. __Return and iterate __— Come back to view cached data, add more keywords, refine strategy

# 3\. Feature Scope

## MVP Features \(In Scope\)

- Project creation and management
- Seed keyword input
- Related keyword expansion via DataForSEO API
- Search volume display
- Keyword difficulty score with Easy/Doable/Hard labels
- Save keywords to project topical map
- View saved topical map
- Data caching to minimize API costs
- Credit\-based usage tracking

## Not in MVP \(Out of Scope\)

- Competitor URL analysis
- Rank tracking over time
- CPC, trend data, or SERP features
- Export functionality \(CSV/PDF\)
- Auto\-clustering by topic/intent
- Domain authority\-based difficulty adjustment
- Free trial or freemium tier
- AI visibility integration

# 4\. Difficulty Thresholds

DataForSEO returns a keyword difficulty score from 0\-100\. For the MVP, use fixed thresholds based on what's realistic for new/small sites:

__Label__

__Score Range__

__Meaning__

__Easy__

0 – 29

New sites can rank with quality content\. Low competition, often long\-tail keywords\.

__Doable__

30 – 59

Achievable with strong content and some backlinks\. May take 3\-6 months\.

__Hard__

60 – 100

Established competition\. Requires significant authority\. Not recommended for new sites\.

# 5\. Data Architecture \(High Level\)

## Core Entities

- __Users __— Account, subscription status, credit balance
- __Projects __— Belong to a user, contain a topical map
- __Keywords __— Global cache of keyword data \(volume, difficulty, last fetched\)
- __Saved Keywords __— Junction table linking keywords to projects \(the topical map\)

## Caching Strategy

- Cache keyword data for 30 days before requiring a refresh
- Share cached data across all users to maximize efficiency
- Only hit DataForSEO API when cache is stale or keyword is new
- Track API costs per user for margin monitoring

# 6\. Credit & Pricing Model

## Pricing

- __$5/month __flat subscription via Stripe
- No free tier, no trial — straight to paid

## Credit System Philosophy

Target 50% margin on API costs\. If DataForSEO costs ~$2\.50 per user per month on average, the $5 price achieves this\. The goal is to let users use the tool as much as possible while maintaining margin\.

## Simplified Credit Approach

Rather than complex per\-action credits, consider these options:

1. __Lookups per month __— e\.g\., 500 keyword lookups/month \(cached lookups don't count\)
2. __Seed expansions per month __— e\.g\., 50 seed keyword expansions/month
3. __Soft limits with usage monitoring __— Allow reasonable use, flag outliers manually at first

## Margin Math Example

DataForSEO Keywords Data API costs roughly $0\.0001\-$0\.001 per keyword depending on endpoint\. If a user does 50 seed expansions returning 100 keywords each = 5,000 keywords/month\. At $0\.0005 per keyword = $2\.50 cost\. $5 revenue \- $2\.50 cost = 50% margin\. Adjust limits based on actual usage data post\-launch\.

# 7\. Success Metrics

## North Star Metric

Keywords saved to topical maps per active user per week\. This measures whether users are finding value \(the aha moment\) and building their strategy\.

## Supporting Metrics

1. __Conversion rate __— Visitors → Paid subscribers
2. __Churn rate __— Monthly subscriber cancellations
3. __API cost per user __— Track to maintain 50% margin
4. __Time to first save __— How quickly new users save their first keyword
5. __Return visits __— Users coming back to their topical maps

# 8\. V2 Feature Backlog

Features explicitly deferred from MVP:

1. Auto\-clustering keywords by topic/intent
2. User\-configurable difficulty thresholds based on domain authority
3. Export to CSV/PDF
4. Competitor URL keyword extraction
5. Rank tracking over time
6. CPC and trend data
7. Manual keyword grouping/tagging
8. Team/agency tier with multiple users

# 9\. Suggested Launch Approach

## Phase 1: Build \(2\-3 weeks\)

- Laravel backend with DataForSEO integration
- Vue\.js frontend with simple, fast UI
- Stripe subscription integration
- Core flow: project → seed → expand → save

## Phase 2: Validate \(2\-4 weeks\)

- Launch to a small group \(indie hackers, solopreneur communities\)
- Monitor API costs closely to validate margin assumptions
- Gather feedback on the aha moment — is Easy/Doable/Hard landing?
- Adjust credit limits if needed

## Phase 3: Grow

- SEO content targeting "affordable keyword research tool"
- Product Hunt launch
- Indie hacker community posts
- Consider V2 features based on user requests

# Summary

This MVP is intentionally minimal: one user type, one core job, one price\. The bet is that solopreneurs will pay $5/month for a tool that immediately shows them which keywords they can realistically win\. Speed and simplicity are the differentiators — not feature parity with enterprise tools\.

Build fast, validate the aha moment, and iterate based on real usage data\.

