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

User enters a seed keyword and immediately sees a structured topical map — clusters of related keywords organized around subtopics, each labeled Easy, Doable, or Hard\. They instantly understand the content landscape and where to focus\.

# 2\. Core User Flow

1. __Sign up __— Create an account
2. __Create a project __— Name it \(e\.g\., "My SaaS Blog" or "Client: Acme Corp"\)
3. __Enter a seed keyword __— The broad topic they want to rank for \(e\.g\., "keto diet"\)
4. __Generate topical map __— System creates clusters of related keywords organized by subtopic
5. __Explore clusters __— Expand clusters to see child keywords with volume and difficulty
6. __Refine with AI __— Optionally use AI to improve clustering and find gaps
7. __Return and iterate __— Come back to explore deeper, expand clusters, refine strategy

# 3\. Feature Scope

## MVP Features \(In Scope\)

- Project creation and management
- Seed keyword input
- Topical map generation via DataForSEO API
- Automatic keyword clustering around parent keywords \(subtopics\)
- Search volume and keyword difficulty display
- Difficulty labels \(Easy/Doable/Hard\)
- Expandable clusters to view child keywords
- AI\-powered cluster refinement \(reorganize keywords\)
- AI\-generated cluster names
- AI gap analysis \(identify missing topics\)

## Not in MVP \(Out of Scope\)

- Competitor URL analysis
- Rank tracking over time
- CPC, trend data, or SERP features
- Export functionality \(CSV/PDF\)
- Domain authority\-based difficulty adjustment
- Free trial or freemium tier
- AI visibility integration
- Stripe subscription \(moved to backlog\)

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

- __Users __— Account
- __Projects __— Belong to a user, contain a topical map
- __Keywords __— Belong to a project with parent/child hierarchy for clustering

## Keyword Hierarchy

Keywords use a self\-referencing parent\_id to form clusters:

- __Seed keyword __— The original input \(is\_seed = true\)
- __Cluster parent __— A keyword with parent\_id = null \(represents a subtopic\)
- __Child keyword __— A keyword with parent\_id referencing its cluster parent

This structure allows the topical map to be displayed as expandable clusters, where each cluster groups related keywords around a central theme\.

# 6\. Success Metrics

## North Star Metric

Topical maps generated per active user\. This measures whether users are finding value in the clustering and exploring their topic landscape\.

## Supporting Metrics

1. __Clusters explored __— How many clusters users expand to view children
2. __AI features used __— Adoption of AI refinement, naming, and gap analysis
3. __Return visits __— Users coming back to their topical maps
4. __Time to first map __— How quickly new users generate their first topical map

# 7\. V2 Feature Backlog

Features explicitly deferred from MVP:

1. User\-configurable difficulty thresholds based on domain authority
2. Export to CSV/PDF
3. Competitor URL keyword extraction
4. Rank tracking over time
5. CPC and trend data
6. Manual keyword grouping/tagging
7. Team/agency tier with multiple users
8. Stripe subscription and billing
9. Cluster expansion \(expand a child keyword into its own cluster\)

# 8\. Suggested Launch Approach

## Phase 1: Build \(2\-3 weeks\)

- Laravel backend with DataForSEO integration
- Vue\.js frontend with simple, fast UI
- Core flow: project → seed → generate topical map → explore clusters
- OpenAI integration for AI\-powered enhancements

## Phase 2: Validate \(2\-4 weeks\)

- Launch to a small group \(indie hackers, solopreneur communities\)
- Gather feedback on the clustering — are the topical maps useful?
- Monitor usage patterns and API costs \(DataForSEO \+ OpenAI\)

## Phase 3: Grow

- SEO content targeting "topical map generator" and "keyword clustering tool"
- Product Hunt launch
- Indie hacker community posts
- Consider V2 features based on user requests

# Summary

This MVP helps solopreneurs build topical authority by generating structured keyword clusters from a single seed keyword\. Instead of a flat list of keywords, users see an organized map of subtopics they can target to become the authority in their niche\.

The core value: enter "keto diet" and instantly see 100\+ clusters like "keto diet types", "keto foods", "keto side effects" — each with related keywords you can rank for\.

Build fast, validate the clustering approach, and iterate based on real usage data\.

