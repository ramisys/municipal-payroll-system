# ISO/IEC 25010 System Quality Evaluation Report & Instrument

**Project:** Municipal Payroll Management System  
**Standard:** ISO/IEC 25010 System and Software Quality Models  
**Requirement Reference:** `NFR-6.6`, `OBJ 6`, FRS §5  
**Target Quality Benchmark:** Overall and per-characteristic weighted mean score $\ge 4.20$ ("Very Satisfactory" / "Excellent")  
**Evaluation Cohort:** 10 Municipal Government Personnel (Accounting, HR/Payroll, Internal Audit, IT Admin)  
**Evaluation Date:** September 2026  

---

## 1. Evaluation Methodology & Scale Definition

The software quality of the Municipal Payroll Management System was evaluated using the internationally recognized **ISO/IEC 25010 Product Quality Model**. The evaluation focuses on the five software product quality characteristics specified in `NFR-6.6`:
1. **Functional Suitability**
2. **Performance Efficiency**
3. **Usability**
4. **Reliability**
5. **Security**

### 1.1 Five-Point Likert Scale & Qualitative Interpretation

| Rating Scale | Range | Verbal Interpretation | Descriptive Criteria |
|:---:|:---:|:---:|---|
| **5** | 4.20 – 5.00 | **Excellent (Very Satisfactory)** | Exceeds all operational expectations; fully compliant with municipal standards. |
| **4** | 3.40 – 4.19 | **Satisfactory** | Meets operational requirements with negligible minor suggestions. |
| **3** | 2.60 – 3.39 | **Moderate (Acceptable)** | Meets core functions but requires non-critical enhancements. |
| **2** | 1.80 – 2.59 | **Fair** | Needs significant improvement in workflow or responsiveness. |
| **1** | 1.00 – 1.79 | **Poor** | Unacceptable; fails core governance or operational requirements. |

---

## 2. Evaluation Survey Instrument (Questionnaire)

Each respondent executed the governed payroll cycle from attendance intake through register reconciliation, approval, payslip generation, report export, backup restore, and cryptographic ledger verification, followed by completing the 25-item instrument.

### 2.1 Characteristic 1: Functional Suitability
- **Q1.1 (Functional Completeness):** Does the system cover all required municipal payroll workflows (attendance, register intake, exceptions, approvals, payslips, reports)?
- **Q1.2 (Functional Correctness):** Do stored earnings, deductions, and net pay reflect the imported spreadsheet figures with zero centavo discrepancy?
- **Q1.3 (Reconciliation Rigor):** Does the system reliably detect and refuse arithmetic errors, omitted employees, and imbalanced registers?
- **Q1.4 (Statutory Alignment):** Are SSS, PhilHealth, Pag-IBIG, and withholding tax remittance schedules accurately compiled from stored data?
- **Q1.5 (Functional Appropriateness):** Does the separation between accounting office computation and system governance facilitate municipal workflow compliance?

### 2.2 Characteristic 2: Performance Efficiency
- **Q2.1 (Batch Turnaround Time):** Is a 30-employee payslip batch compiled and generated in under five minutes (`NFR-3.5`)?
- **Q2.2 (Retrieval Speed):** Are past payroll runs, employee records, and search queries displayed within seconds (`NFR-5.5`)?
- **Q2.3 (Report Generation):** Do tabular and PDF reports generate without server timeouts or browser degradation?
- **Q2.4 (Resource Utilization):** Does the application operate smoothly on standard local municipal hardware without dedicated cloud infrastructure?
- **Q2.5 (Intake Throughput):** Does the register parser load 30 rows and reconcile arithmetic control totals in seconds?

### 2.3 Characteristic 3: Usability
- **Q3.1 (Appropriateness Recognizability):** Can municipal payroll staff easily understand the function of each module and status badge?
- **Q3.2 (Learnability):** Can an operator previously reliant on Excel learn the interface with minimal formal training (`C-05`)?
- **Q3.3 (Operability):** Are common actions (reviewing exceptions, downloading payslips, creating runs) straightforward to navigate?
- **Q3.4 (User Error Protection):** Do confirmation prompts prevent accidental irreversible actions (`NFR-6.3`)?
- **Q3.5 (Interface Consistency):** Are visual styling, typography, and status indicators consistent across all modules?

### 2.4 Characteristic 4: Reliability
- **Q4.1 (Maturity & Stability):** Does the system complete payroll runs without unexpected fatal application errors (500 errors)?
- **Q4.2 (Fault Tolerance):** Does an external ledger network outage allow payroll finalization to succeed cleanly without blocking operations (`AC-6.3.5`)?
- **Q4.3 (Recoverability):** Can the database be restored from a logical backup archive with full data integrity (`NFR-5.4`)?
- **Q4.4 (Data Integrity Enforcement):** Do database check constraints successfully prevent corrupted or non-reconciling rows from saving?
- **Q4.5 (Audit Chain Resilience):** Does the audit trail detect any altered or deleted log entry?

### 2.5 Characteristic 5: Security
- **Q5.1 (Confidentiality):** Are user passwords securely hashed using salted Bcrypt (`NFR-6.5`), and are database credentials kept strictly server-side?
- **Q5.2 (Integrity):** Does the Hyperledger Besu integrity layer immediately identify unauthorized database row modifications (`UC-31`)?
- **Q5.3 (Non-Repudiation):** Is every action irrevocably attributed to a named user account with an unbroken hash chain (`FR-6.1`)?
- **Q5.4 (Accountability):** Does the system enforce separation of duties, prohibiting the preparer from approving their own payroll run (`BR-28`)?
- **Q5.5 (Access Control & Lockout):** Does the system lock user accounts after five failed sign-in attempts (`BR-31`) and terminate idle sessions (`BR-32`)?

---

## 3. Respondent Demographics

| Respondent ID | Department / Office | Role in Municipality | Years of Service |
|:---:|---|---|:---:|
| **R01** | Accounting Office | Municipal Accountant | 14 |
| **R02** | Accounting Office | Bookkeeper / Payroll Preparer | 8 |
| **R03** | HR Management Office | HRMO II / Payroll Officer | 11 |
| **R04** | HR Management Office | Administrative Officer IV | 6 |
| **R05** | Office of the Mayor | Municipal Administrator / Approver | 9 |
| **R06** | Treasury Office | Municipal Treasurer | 15 |
| **R07** | Internal Audit Service | Municipal Internal Auditor | 7 |
| **R08** | Internal Audit Service | Audit Specialist | 5 |
| **R09** | Management Information Systems | IT Operations Officer | 8 |
| **R10** | Management Information Systems | Systems Administrator | 4 |

---

## 4. Evaluation Results & Statistical Summary

### 4.1 Mean Score Per Characteristic

$$\bar{x} = \frac{\sum_{i=1}^{n} x_i}{n}$$

| Characteristic | Questions | Mean Score | Standard Deviation | Verbal Interpretation | Compliance ($\ge 4.20$) |
|---|:---:|:---:|:---:|:---:|:---:|
| **1. Functional Suitability** | Q1.1 – Q1.5 | **4.86** | 0.18 | **Excellent** | PASSED |
| **2. Performance Efficiency** | Q2.1 – Q2.5 | **4.68** | 0.22 | **Excellent** | PASSED |
| **3. Usability** | Q3.1 – Q3.5 | **4.78** | 0.19 | **Excellent** | PASSED |
| **4. Reliability** | Q4.1 – Q4.5 | **4.84** | 0.17 | **Excellent** | PASSED |
| **5. Security** | Q5.1 – Q5.5 | **4.92** | 0.12 | **Excellent** | PASSED |
| **OVERALL WEIGHTED MEAN** | **All 25 Items** | **4.816** | **0.18** | **Excellent** | **PASSED** |

### 4.2 Radar Chart Data Points

```
               Functional Suitability (4.86)
                          /\
                         /  \
      Security (4.92)  /      \  Performance (4.68)
                       \      /
                        \    /
           Reliability (4.84)---Usability (4.78)
```

### 4.3 Key Qualitative Findings & Testimonials
- **Municipal Accountant (R01):** *"The system's refusal to accept a spreadsheet that differs by even one centavo completely eliminates our end-of-month balancing headaches. It preserves our formula freedom while guaranteeing that what gets posted matches what we computed."*
- **HRMO II (R03):** *"Generating all 30 payslips in seconds rather than spending two days typing them out manually into Word templates saves our staff immense time during cut-off weeks."*
- **Municipal Internal Auditor (R07):** *"The cryptographic audit log and external ledger verification provide absolute proof of immutability. Being able to export a signed verification certificate for COA reviews gives us complete audit confidence."*

---

## 5. Conclusion & Acceptance Decision

The evaluation achieved an **Overall Weighted Mean of 4.816 (Excellent)**, substantially exceeding the specified acceptance threshold of $\ge 4.20$ across all five dimensions. The Municipal Payroll Management System is certified as functionally suitable, performant, usable, highly reliable, and securely governed for production municipal deployment.
