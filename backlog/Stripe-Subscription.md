__MILESTONE 5 OF 5__

Stripe Subscription

*Estimated effort: 2\-3 days*

# Overview

Implement Stripe subscription billing using Laravel Cashier\. Users must subscribe to the $5/month plan to access the app's features\. This milestone adds the paywall, checkout flow, and subscription management\. After this milestone, the MVP is complete and ready for paying customers\.

# Prerequisites

- Milestone 5 complete \(full product functionality working\)
- Stripe account created
- Stripe test API keys available

# Deliverables

1. Laravel Cashier installation and configuration
2. Stripe product and price created \($5/month\)
3. Checkout/subscribe page
4. Paywall middleware blocking non\-subscribers
5. Subscription management page \(view status, cancel\)
6. Webhook handling for subscription events

# Technical Requirements

## 1\. Stripe Setup

__In Stripe Dashboard:__

1. Create a Product \(e\.g\., "Keyword Research Pro"\)
2. Create a Price: $5\.00 USD, recurring monthly
3. Note the Price ID \(starts with price\_\)
4. Get API keys \(publishable and secret\)

__Environment variables:__

- STRIPE\_KEY=pk\_test\_\.\.\.
- STRIPE\_SECRET=sk\_test\_\.\.\.
- STRIPE\_WEBHOOK\_SECRET=whsec\_\.\.\.
- STRIPE\_PRICE\_ID=price\_\.\.\.

## 2\. Laravel Cashier Setup

- Install: composer require laravel/cashier
- Run Cashier migrations \(creates subscriptions, subscription\_items tables\)
- User model already has Cashier fields from Milestone 1
- Add Billable trait to User model \(if not already added\)

## 3\. Subscription Flow

The user flow after registration:

1. User registers \(already working\)
2. User is redirected to subscription/checkout page
3. User enters payment details via Stripe Checkout or embedded form
4. On success, user is redirected to /projects
5. If user tries to access protected routes without subscription, redirect to checkout

## 4\. Checkout Implementation

Two options — choose one:

__Option A: Stripe Checkout \(recommended for MVP\)__

- Redirect user to Stripe\-hosted checkout page
- Use Cashier's checkout\(\) method
- Simplest to implement, handles all payment UI
- Set success\_url and cancel\_url

__Option B: Embedded payment form__

- Build custom checkout page with Stripe Elements
- More control over UX but more work
- Can do this later if needed

## 5\. Routes

__Method__

__URI__

__Purpose__

GET

/subscribe

Show subscription/pricing page

POST

/subscribe

Initiate Stripe Checkout

GET

/subscribe/success

Checkout success redirect

GET

/billing

Subscription management page

POST

/billing/cancel

Cancel subscription

POST

/billing/resume

Resume cancelled subscription

POST

/stripe/webhook

Stripe webhook endpoint

## 6\. Paywall Middleware

Create middleware to protect subscriber\-only routes:

- Check if user has active subscription: $user\->subscribed\('default'\)
- If not subscribed, redirect to /subscribe
- Apply middleware to all project and keyword routes
- Allow access to /subscribe, /billing, /logout without subscription

## 7\. Subscription Management Page

The /billing page shows:

- Current plan: "Pro \- $5/month"
- Status: Active, Cancelled \(ends on date\), Past Due
- Next billing date
- Cancel subscription button \(with confirmation\)
- Resume subscription button \(if cancelled but still in grace period\)
- Link to Stripe Customer Portal \(optional, for updating payment method\)

## 8\. Webhooks

Configure Stripe webhooks to handle subscription lifecycle events:

- customer\.subscription\.created
- customer\.subscription\.updated
- customer\.subscription\.deleted
- invoice\.payment\_failed
- invoice\.payment\_succeeded

Laravel Cashier handles most webhook events automatically\. You may want to add custom handling for payment\_failed to send an email notification\.

## 9\. Grace Period Handling

- When user cancels, subscription stays active until end of billing period
- Use $user\->subscription\('default'\)\->onGracePeriod\(\) to check
- Show "Your subscription ends on \[date\]" message
- Allow resume before period ends

# Acceptance Criteria

The milestone is complete when:

1. New user is redirected to /subscribe after registration
2. User can complete checkout with test card \(4242 4242 4242 4242\)
3. After successful payment, user can access /projects and all features
4. Non\-subscribed user cannot access protected routes \(redirected to subscribe\)
5. User can view subscription status on /billing
6. User can cancel subscription
7. Cancelled user retains access until end of billing period
8. User can resume cancelled subscription before period ends
9. Webhooks are configured and receiving events
10. Subscription data is stored correctly in database

# Testing Tips

- Use Stripe test mode throughout development
- Test card: 4242 4242 4242 4242, any future date, any CVC
- Use Stripe CLI to forward webhooks locally: stripe listen \-\-forward\-to localhost/stripe/webhook
- Test declined cards: 4000 0000 0000 0002
- Verify subscription in Stripe Dashboard after successful payment

# Notes

- Consider adding a simple landing/marketing page at / that links to /register
- Stripe Customer Portal can handle payment method updates — link to it from /billing
- Make sure to switch to live Stripe keys before launch
- Consider sending welcome email after successful subscription \(defer to V2 if complex\)
- This milestone completes Phase 1 — the MVP is ready for launch\!

# 🎉 Phase 1 Complete

After this milestone, you have a complete, monetized MVP: users can register, subscribe for $5/month, create projects, research keywords, see Easy/Doable/Hard labels, save keywords to their topical map, and manage their subscription\. Time to get some users\!

