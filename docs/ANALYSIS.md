# Vaccination Management System: Analysis

Aptech eProject. Admin panel: built by teammates. **User panel / frontend (Parent + Hospital): our task.**

## Core problem
Manual vaccination (e.g. via schools) means children miss doses. Solution: an e-vaccination portal where parents register children, see due dates, and book hospital slots.

## Roles and modules
| Role | Features |
|---|---|
| Admin (done by group) | All child details, vaccination date/time, date-wise reports, vaccine availability list, approve/reject parent requests, hospital CRUD + list, booking details |
| **Parent (ours)** | Register & login, child details (add/update), vaccination dates (dashboard notifications), search hospitals & book slot, request for hospital, report of vaccinations taken, my profile |
| **Hospital (ours)** | Register & login (name, address, location), view appointments, update status to Vaccinated / Not vaccinated |

## Flow
Parent books -> Admin approves/rejects -> Hospital receives appointment -> Hospital marks Vaccinated -> Parent sees report.

## Mandatory rules (from eProject spec)
- Stack named in spec: PHP, MySQL, Apache (XAMPP). Frontend must integrate with it.
- Every code block commented; logic explained.
- Documentation: certificate, TOC, problem definition, CRS, project plan, ER diagrams, algorithms, GUI standards, interface design, task sheet, review report, unit-test checklist, final checklist.

## Open decisions
1. Stack: React + Tailwind + Framer Motion (Vite) talking to PHP/MySQL JSON API, vs. plain PHP pages with Tailwind/GSAP.
2. Who owns the API/DB? Need the admin team's schema.
3. Hospital portal: ours too?
