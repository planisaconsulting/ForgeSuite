# Contractor portal

Contractors are a party of their own. A contractor can point at an existing supplier. The portal is `/contractor` and uses `contractor_users`, not staff users and not customer portal users.

The home page lists work that was sent to that company: items waiting for a response, upcoming work, active work, and finished work. A draft work order is not visible.

The contractor sees the scope, site, contact, instructions, agreed amount, and files linked to that work order. The page does not select quote value, margin, gross profit, internal notes, other jobs, or debtor balances.

Portal codes `contractor.work.view`, `contractor.work.accept`, `contractor.work.update`, `contractor.photos.upload`, `contractor.documents.view`, and `contractor.completion.submit` are rows on the contractor user. They are not staff permissions.

Another contractor opening the same work order gets no record. A production file that is not linked to their work order is refused.
