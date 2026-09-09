# Protocol: Advisory Vacancy Scoring

## Objective
Pre-flight evaluation of incoming job vacancies before triggering the multi-agent consensus loop.
The purpose is to alert the user to potential mismatches in location, salary, or core competency without introducing unnecessary bottlenecks.

> **Principle**: Scoring is purely advisory. If the user explicitly confirms proceeding ("ok", "proceed", "continue"), the pipeline starts immediately.

---

## 1. Evaluation Dimensions

### A. Commute & Work Model (Base: Candidate Residence)
* 🟢 **Green (Ideal)**:
  - Within 40 km of base.
  - Or 100% Remote / Home Office.
* 🟡 **Yellow (Acceptable with hybrid terms)**:
  - 40–70 km with hybrid schedule (2–3 days Home Office).
* 🔴 **Red (Requires user confirmation)**:
  - 100% on-site presence at distance > 70 km.
  - Distant federal states without remote or relocation options.

### B. Compensation Benchmark (Base: Target Salary)
* 🟢 **Green**: Salary unstated (negotiable) or salary range matches/exceeds candidate target.
* 🟡 **Yellow**: Within 10–15% below target (negotiable based on benefits and bonus).
* 🔴 **Red (Requires user confirmation)**: Hard ceiling significantly below candidate benchmark.

### C. Competence Alignment
* 🟢 **Green (Strong Match)**:
  - Direct overlap with commercial B2B experience, ERP systems, procurement, or IT/SQL data management.
* 🟡 **Yellow (Adjacent Transfer)**:
  - Adjacent functions (logistics coordination, project operations, technical interface).
* 🔴 **Red (Incompatible Foundation)**:
  - Regulated professions requiring non-transferable formal degrees or licenses (attorney, medical doctor, licensed auditor).

---

## 2. Advisory Report Format

When yellow or red dimensions exist, provide a concise summary:
```markdown
🔎 **Advisory Vacancy Scoring:**
- 📍 **Location**: [City] (~XX km) — [On-site / Hybrid / Remote]
- 💰 **Compensation**: [Indicated Range vs Target]
- 🎯 **Focus**: [Business-First / IT-First / Adjacent]
⚠️ **Points for Attention**: [Concise 1-2 bullet points]

Ready to launch pipeline. Proceed?
```
