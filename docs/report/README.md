# Project Report

The written final-year project report for the GreenAcres Farm Management System.

| File | What it is |
|---|---|
| `Farm_Management_System_Report.docx` | The editable Word document — edit this one |
| `Farm_Management_System_Report.pdf` | Print-ready PDF generated from the Word file |
| `img/` | Every screenshot and diagram, as separate PNGs — including `kstu_crest.png` |

**Prepared for:** Boatemaa Kyerewaa Jantuah · Index Number 052420760094
**Programme:** Diploma in Information Technology (DIT)
**Length:** 59 pages

---

## Structure

| Section | Pages | Orientation |
|---|---|---|
| Cover, declaration, dedication, acknowledgement, abstract, contents, lists | i – viii | Portrait |
| Chapter One — Introduction | 1 – 5 | Portrait |
| Chapter Two — Literature Review | 6 – 10 | Portrait |
| Chapter Three — System Analysis and Design | 11 – 23 | Portrait |
| Chapter Four — Implementation and Testing | 24 – 34 | Portrait |
| Chapter Five — Summary, Conclusion, Recommendations | 35 – 38 | Portrait |
| References | 39 | Portrait |
| Appendix A — Full-page diagrams and screenshots | 40 – 45 | **Landscape** |
| Appendix B — Database schema | 46 – 47 | Portrait |
| Appendix C — Selected source code | 48 – 49 | Portrait |
| Appendix D — Installation and user guide | 50 – 51 | Portrait |

The report contains 12 figures, 13 tables and 6 full-page landscape plates.

---

## The cover crest

The cover carries the official Kumasi Technical University emblem, taken from
the university's own website (`kstu.edu.gh`) and cleaned of the transparency
checkerboard the source file had baked into it. The cleaned image is kept at
`img/kstu_crest.png` if it is ever needed again.

If your department prefers a different version of the crest — the horizontal
lockup with the wordmark, for instance — replace the image on the cover page.

---

## Before submitting — two things to check

1. **The cover details.** The faculty is given as *Faculty of Applied Sciences
   and Technology* and the department as *Department of Computer Science*.
   Correct these if your department is named differently, and fill in the
   supervisor's name on the declaration page.

2. **Page numbers after editing.** The Table of Contents, List of Figures, List
   of Tables and List of Plates carry page numbers written as ordinary text, not
   automatic fields. If you add or remove material the numbers will drift, so
   correct them by hand afterwards.

---

## A note on the references

Every work in the reference list is a real, verifiable publication — Codd on the
relational model, Nielsen on usability heuristics, the OWASP Top Ten, the PHP and
MySQL manuals, and standard textbooks on database systems and software
engineering. Nothing was invented.

They are, however, general rather than local. Ask your supervisor whether the
department expects Ghanaian or West African sources on agricultural extension
and smallholder record keeping; if so, add them and cite them in Sections 2.3
and 2.4, where the argument about local practice is made.

---

## Regenerating the PDF

Open the Word document and use **File → Export → Create PDF/XPS**, or on a
machine with LibreOffice installed:

```bash
soffice --headless --convert-to pdf Farm_Management_System_Report.docx
```
