# Vaccination Management System: Analysis (corrected)

Aptech eProject. Stack is FIXED: plain PHP pages + MySQL + Apache (XAMPP).
No React, no JSON-API layer, no PHP framework.

## Core problem
Manual vaccination (e.g. via schools) means children miss doses. Solution:
an e-vaccination portal where parents register children, see due dates,
and book hospital slots.

## Ownership
| Panel | Owner |
|---|---|
| Admin | Teammates (already built; schema and code to be shared) |
| **Parent** | **Us** |
| **Hospital** | **Us** |

Rule: do NOT modify admin files or admin-owned tables.

## Roles and modules
| Role | Features |
|---|---|
| Admin (teammates) | All child details, vaccination date/time, date-wise reports, vaccine availability, approve/reject parent requests, hospital CRUD + list, booking details |
| **Parent (ours)** | Register/login, add/update child, dashboard of upcoming vaccinations, search hospitals and book slot, view bookings, report of vaccinations taken, my profile |
| **Hospital (ours)** | Register/login (name, address, location), view appointments assigned to it, update status to Vaccinated / Not vaccinated |

## Flow
Parent books (Pending) -> Admin approves/rejects -> Hospital sees approved
appointment -> Hospital marks Vaccinated / Not vaccinated -> Parent report
and Admin reports reflect it.

## Decisions made
1. "Request for hospital" and "Book Hospital" are ONE booking flow.
2. Booking status lifecycle: Pending -> Approved | Rejected ->
   Vaccinated | Not Vaccinated. Must match the admin panel's values.
3. Dashboard "notifications" = a query for vaccines due in the next
   30 days or overdue. No email/SMS.
4. We create our own vaccine_schedule table (age-based lookup).
5. Front-end libraries (e.g. three.js) are allowed but come LAST, are
   isolated in their own JS file, and are hosted locally (no CDN).

## Data ownership
- Ours: parents, children, hospital login data (if not already in the
  admin's hospitals table), vaccine_schedule
- Shared / admin-owned (match their schema, do not alter): hospitals,
  vaccines, bookings

## Mandatory rules (eProject spec)
- Every code block commented; logic explained.
- Documentation: certificate, TOC, problem definition, CRS, project plan,
  ER diagrams, algorithms, GUI standards, interface design, task sheet,
  review report, unit-test checklist, final checklist.

## Open items
1. Admin schema and code (waiting on team).
2. Does the admin's hospitals table already hold hospital login credentials,
   or do we add a separate hospital_users table?
3. Exact submission format and demo/viva requirements.
