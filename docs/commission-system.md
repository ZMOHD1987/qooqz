# Commission System Documentation

## Overview

This document describes the full architecture of the Commission
Management System. The system is designed for multi-tenant marketplace
platforms and supports:

-   Commission Transactions
-   Invoice Generation
-   Invoice Items Snapshot
-   Payments Tracking
-   Credit Notes Handling
-   Financial Balance Snapshot
-   Audit Logging

------------------------------------------------------------------------

## 1. Core Architecture

### Tenants

Represents platform tenants in a multi-tenant environment.

### Entities

Represents vendors/stores under each tenant.

------------------------------------------------------------------------

## 2. Commission Transactions

Tracks every financial commission-related operation including: - Sales -
Refunds

Key Fields: - order_amount - commission_amount - vat_amount -
net_commission - status (pending, invoiced, paid, cancelled) - is_locked
(prevents modification after invoicing)

------------------------------------------------------------------------

## 3. Commission Invoices

Generated periodically (monthly, quarterly, custom).

Contains: - period_start / period_end - totals (commission, VAT, grand
total) - payment tracking (amount_paid) - status lifecycle (draft →
issued → partially_paid → paid)

Unique Constraint: (tenant_id, entity_id, period_start, period_end)

------------------------------------------------------------------------

## 4. Invoice Items (Snapshot)

Each invoice contains frozen copies of: - transaction details - amounts
at time of invoicing

Prevents historical data corruption.

------------------------------------------------------------------------

## 5. Payments

Tracks all invoice payments: - payment_number - payment_method -
amount_paid - cancellation support

Prevents overpayment via validation logic.

------------------------------------------------------------------------

## 6. Credit Notes

Handles refunds or post-invoice adjustments.

Linked to: - specific invoice - specific transaction

Prevents duplicate credit per transaction.

------------------------------------------------------------------------

## 7. Financial Balances (Snapshot Ledger)

Pre-aggregated financial summary per entity: - total_sales -
total_commission - total_paid - total_balance - pending_balance -
invoiced_balance

Optimized for dashboards and performance.

------------------------------------------------------------------------

## 8. Audit Log

Tracks all critical actions: - invoice creation - payment recording -
credit issuance - cancellations

Ensures financial traceability.

------------------------------------------------------------------------

## Security & Integrity

-   Foreign key constraints
-   Row locking for invoice generation
-   Overpayment prevention
-   Immutable invoiced transactions
-   Transaction-safe stored procedures

------------------------------------------------------------------------

## Deployment Order

1.  Core Tables
2.  Financial Tables
3.  Audit Tables
4.  Triggers
5.  Stored Procedures
6.  Views

------------------------------------------------------------------------

End of Document
