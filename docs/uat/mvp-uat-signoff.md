# MVP UAT Signoff

Status: blocked until business users execute UAT.

## Required Participants

- Marketplace admin
- Vendor user
- Buyer user
- Finance approver
- Support/moderation approver

## UAT Scenarios

1. Vendor applies, is rejected with reason, reapplies, and is approved.
2. Vendor completes Stripe/KYC onboarding.
3. Vendor creates product and uploads media.
4. Buyer places multi-vendor order.
5. Vendor ships suborder with tracking.
6. Buyer requests refund.
7. Admin approves refund and verifies commission reversal.
8. Finance creates payout batch and verifies Stripe test transfer.
9. Admin reviews reconciliation and trial balance.
10. Support verifies notification delivery and audit log.

## Signoff Evidence

- UAT date
- tester names
- test tenant/site URL
- defects found
- retest evidence
- final signoff decision

## Current Blocker

No external beta users or business approvers are available in this local environment. This gate cannot be marked Done until the scenarios above are executed by the user or assigned testers.
