# EduBridge Documentation Index

This folder is the primary knowledge base for the EduBridge project.

If you are new to the codebase, read in this order:

1. `00_Documentation_Index.md` (this file)
2. `01_Project_Overview.md`
3. `02_Architecture_and_Flow.md`
4. `03_Setup_Configuration_and_Runbook.md`
5. `04_Codebase_Map.md`
6. `05_Routes_and_API_Reference.md`
7. `06_Database_Models_and_Data_Guide.md`
8. `07_Backend_Modules_Explained.md`
9. `08_Frontend_Pages_and_UI_System.md`
10. `09_Operations_Testing_and_Release.md`
11. `10_Troubleshooting_and_Known_Issues.md`
12. `11_Extensibility_and_Future_Roadmap.md`
13. `12_Demo_Data_Reference.md`

---

## Existing Legacy Notes

Two legacy documents are still present for design direction and phase notes:

- `EduBridge_DesignBlueprint.txt`
- `EduBridge_PhaseGuide_1.txt`

These are useful as historical context, but the markdown docs listed above are the authoritative reference for the current implementation.

---

## Audience Guide

- New developer onboarding: Start at `01`, then `03`, then `04`.
- Backend/API work: Focus on `05`, `06`, `07`.
- Frontend/UI work: Focus on `08` and `02`.
- DevOps/QA/release: Focus on `09` and `10`.
- Product planning and future features: Focus on `11`.
- Demo/testing dataset usage: Focus on `12`.

---

## Documentation Conventions

- Paths are workspace-relative from project root.
- Route names and endpoint paths are shown exactly as implemented.
- Environment variable names are shown exactly as used in `.env`/`config`.
- Behavior notes indicate current implementation, not planned behavior.

---

## Maintenance Rule

Whenever code changes significantly (routes, models, major flows), update these docs in the same pull request or commit.
