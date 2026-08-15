# Project Report

The written final-year project report for the GreenAcres Farm Management System.

| File | What it is |
|---|---|
| `Farm_Management_System_Report.docx` | The editable Word document — edit this one |
| `Farm_Management_System_Report.pdf` | Print-ready PDF generated from the Word file |
| `img/` | Every screenshot and diagram used in the report, as separate PNGs |

**Prepared for:** Boatemaa Kyerewaa Jantuah · Index Number 052420760094
**Programme:** Diploma in Information Technology (DIT)

---

## Structure

| Section | Pages | Orientation |
|---|---|---|
| Cover, declaration, dedication, acknowledgement, abstract, contents, lists | i – xii | Portrait |
| Chapter One — Introduction | 1 – 6 | Portrait |
| Chapter Two — Literature Review | 7 – 13 | Portrait |
| Chapter Three — System Analysis and Design | 14 – 34 | Portrait |
| Chapter Four — Implementation and Testing | 35 – 54 | Portrait |
| Chapter Five — Summary, Conclusion, Recommendations | 55 – 59 | Portrait |
| References | 60 – 61 | Portrait |
| Appendix A — Full-page screenshots and diagrams | 62 – 93 | **Landscape** |
| Appendix B — Database schema | 94 – 96 | Portrait |
| Appendix C — Selected source code | 97 – 100 | Portrait |
| Appendix D — Installation and user guide | 101 – 105 | Portrait |

The report body (Chapters One to Five plus references) runs to 61 pages. The
appendices carry the full screenshot gallery in landscape so every interface
image stays legible when printed.

---

## Before submitting — three things to do

1. **Insert the university crest.** The cover page has a dashed placeholder box
   reading *"[ INSERT UNIVERSITY CREST HERE ]"*. Click it, delete the box and
   insert the logo image in its place.

2. **Check the cover details.** The faculty is given as *Faculty of Applied
   Sciences and Technology* and the department as *Department of Computer
   Science*. Correct these if your department is named differently, and fill in
   the supervisor's name on the declaration page.

3. **Update the fields after any edit.** If you add or remove text, the page
   numbers in the Table of Contents, List of Figures, List of Tables and List of
   Plates will no longer match. They are written as ordinary text rather than
   automatic fields, so correct them by hand, or select the whole document and
   press `Ctrl + A` then `F9` after converting them to fields.

---

## A note on the references

Every work in the reference list is a real, verifiable publication — Codd on the
relational model, Nielsen on usability heuristics, the OWASP Top Ten, the PHP and
MySQL manuals, and standard textbooks on database systems and software
engineering. Nothing was invented.

They are, however, general rather than local. Ask your supervisor whether the
department expects Ghanaian or West African sources on agricultural extension
and smallholder record keeping; if so, add them to the list and cite them in
Sections 2.3 and 2.5, where the argument about local practice is made.

---

## Regenerating the PDF

Open the Word document and use **File → Export → Create PDF/XPS**, or on a
machine with LibreOffice installed:

```bash
soffice --headless --convert-to pdf Farm_Management_System_Report.docx
```
