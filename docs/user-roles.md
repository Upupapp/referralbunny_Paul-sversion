# User Roles — Referral Bunny

## Types of Users

### Super Admin
The platform owner. Has access to all tenants across the system. Can create, suspend, and manage any workspace. Receives system-level notifications when new workspaces are created. Not tied to any single tenant.

### Tenant Owner / Admin / Manager
Operates a referral program workspace. Manages the full pipeline: creates and updates deals, approves stage moves and archive requests, sets commissions, invites referrers and partners, and views all financial data. Managers have the same deal-level access as admins but cannot manage billing or workspace settings.

### Referrer (also called Reseller)
A person who brings in business leads and earns a commission when those leads close. Referrers have their own portal and are the primary deal creators in the system.

### Partner
A person who supports a deal that a referrer owns — for example, a technical consultant, closer, or implementation specialist. Partners are added to specific deals by the referrer or admin. They have a limited, deal-scoped view of the system.

---

## Key Functions of a Referrer

- **Submit deals** — Create new leads and assign them to a tenant's pipeline.
- **Track pipeline progress** — View deal stage, days remaining, and status across all their deals.
- **Advance stages** — Move a deal to the next pipeline stage directly, or request admin approval when documentation is incomplete.
- **Request archive** — Submit a deal for closure with a reason; admin approves or rejects.
- **View commissions** — See their commission pool, split percentage, and payment status (pending / locked / paid) per deal.
- **Add co-referrers** — Split commission with another referrer on a deal and define each party's percentage.
- **Add partners** — Assign partners to deals and define partner commission shares.
- **Message admins** — Send and receive messages with tenant admins and managers.
- **Calendar** — View deal expiry dates and assigned tasks on a monthly calendar.
- **Activity log** — Review a full history of events on their deals (stage moves, notes, commission changes, imports).
- **Request forms** — Access and submit public request forms published by the tenant.
- **Tasks** — View and complete tasks assigned to them by the admin team.

---

## Key Functions of a Partner

- **View assigned deals** — See the name, pipeline stage, status, days remaining, and total deal value of deals they have been added to.
- **View own commission** — See their individual commission split amount and payment status. They cannot see other partners' shares or the full commission pool breakdown.
- **Add deal notes** — Leave notes on a deal visible to the referrer and admin team.
- **Message referrer and admins** — Start or continue conversations on a deal thread, and send direct messages to admin or manager.
- **Calendar** — View expiry dates for their assigned deals.
- **Request forms** — Access and submit request forms published by the tenant.
- **Profile** — Update their display name, photo, and password.

## Partner Limitations

- **Cannot create deals.** Partners are added to existing deals by the referrer or admin; they cannot submit new leads.
- **Cannot change pipeline stages.** Deal stage movement is restricted to referrers and admins.
- **Cannot see full financials.** `base_cost`, `added_amount`, and the full commission pool are never exposed to partners. They only see their own computed peso amount.
- **Cannot see other partners' shares.** Each partner sees only their own split percentage and amount on a deal.
- **Cannot manage tasks.** There is no task system in the partner portal; partners cannot create, assign, or complete tasks.
- **Cannot archive deals.** Archive requests are a referrer-only action.
- **Cannot manage co-referrers.** Adding or adjusting referrer commission splits is outside partner scope.
- **Read-only on deal details.** Partners see a curated deal summary — name, stage, status, deal value, referrer name, and location context only.
