# Behavioral Diagrams

**Project:** Payroll Management System
**Document:** Sequence, Activity, and State Machine Diagrams
**Version:** 1.1
**Date:** August 30, 2026
**Baseline:** B1 — frozen August 30, 2026 · see [baseline.md](./baseline.md)
**Traces to:** [Use Case Model](./use-case-model.md) → [Functional Requirements Specification](./functional-requirements-specification.md) → [Problem-to-Requirements Matrix](./problem-requirements-matrix.md)

---

## Document control

| | |
|---|---|
| Sequence diagrams | 4 (UC-18, UC-24, UC-25, UC-31) |
| Activity diagrams | 3 (UC-18, UC-24, UC-25 with UC-26) |
| State machine diagrams | 1 (payroll run lifecycle) |
| Use cases modelled | Fully — UC-18, UC-24, UC-25, UC-26, UC-31. As guarded transitions in Figure 7 — UC-17 (main and A2 cancel), UC-22, UC-23. Included — UC-I6 within Figures 3 and 8. |
| Participants specified | 14 |

---

# 1. About these diagrams

## 1.1 Why these use cases

The use case model specifies thirty-one use cases. Five are modelled here: four carry nearly all the risk in the payroll cycle, and the fifth is the integrity check that proves the rest were not altered.

| Use case | Why it is modelled |
|---|---|
| **UC-18 Compute payroll run** | Where the money is calculated. It has the fixed computation order of BR-13, two included use cases, and six exception flows. If any diagram in the manuscript earns its page, it is this one. |
| **UC-24 Approve or return payroll run** | Where the separation of duty is enforced. BR-28 — the preparer may not approve — is a control that exists only as a runtime check, so it must be visible in the model. |
| **UC-25 Finalize payroll run** | Where the record becomes immutable. Everything about reproducibility (C-04) and period locking (FR-4.5) happens in this one transition. |
| **UC-26 Reverse finalized payroll run** | The only way out of `Finalized`, and the one path that must leave permanent evidence. Modelled in the activity view alongside UC-25 (Figure 6). |
| **UC-31 Verify payroll record integrity** | Where the system is asked to prove a stored record has not changed. Its three outcomes must stay distinct — a mismatch, a pending anchor, and an unreachable ledger mean different things (Figure 8). |

The remaining twenty-six use cases are adequately specified by their main success scenarios in the use case model. Drawing all of them would produce volume, not clarity.

## 1.2 What each diagram type shows

| Diagram | Question it answers | Emphasis |
|---|---|---|
| **Sequence** | Which components talk to each other, in what order, and what each returns | Interaction between objects over time |
| **Activity** | What steps occur, who performs each, and where the flow branches | Control flow across actors |
| **State machine** | What states a payroll run can hold and which transitions are legal | Lifecycle constraints |

The sequence and activity diagrams for one use case describe the same behavior from two angles. They are deliberately consistent: every alternate and exception flow in the use case specification appears in both.

## 1.3 Notation

Diagrams are written in Mermaid so they render in the repository.

**Sequence diagrams** — solid arrows are calls, dashed arrows are returns. `alt` / `else` marks mutually exclusive paths, `opt` an optional one, and `loop` a repetition. Activation bars show when a participant is executing.

**Activity diagrams** — Mermaid has no native UML activity notation, so these are drawn as flowcharts with one labelled lane per actor. Diamonds are decisions, rounded ends are start and stop nodes. This is a faithful representation of control flow; the redraw for Chapter III should use proper UML swimlanes with the same content.

> **For the manuscript.** As with the use case diagrams, these need vector redraws at the department's required figure format. The content is authoritative; the redraw is formatting.

## 1.4 Participants

The sequence diagrams name components, not classes. Their boundaries follow the module structure of FRS §2.2, and the design documentation may realize each as one or several classes.

| Participant | Responsibility | Realizes |
|---|---|---|
| `Payroll Run UI` | Screens for creating, computing, reviewing, and transitioning runs | M4, M5 |
| `PayrollRunController` | Orchestrates a run's computation and state transitions | M4, M5 |
| `ComputationEngine` | Executes the arithmetic of BR-01 through BR-23 | M4 |
| `EmployeeRepository` | Retrieves employee records and dated compensation profiles | M2 |
| `AttendanceRepository` | Retrieves the attendance and leave summary for a cut-off | M3 |
| `StatutoryScheduleService` | Selects and applies the schedule in force — UC-I5 | M1, M4 |
| `ExceptionEvaluator` | Evaluates rules EX-01 to EX-10 — UC-I4 | M5 |
| `PayrollRepository` | Persists runs, lines, earnings, deductions, and transitions | M7 |
| `AuthorizationService` | Enforces the FR-6.2 permission matrix and BR-28 — UC-I3 | M1 |
| `AuditService` | Writes append-only audit entries — UC-I2 | M1 |
| `NotificationService` | Informs an actor that a run awaits their action | M5 |
| `PayslipService` | Generates payslips from finalized runs | M6 |
| `LedgerAnchorService` | Queues and transmits integrity anchors — UC-I6 | M1 |
| `IntegrityVerificationService` | Recomputes hashes and compares them with the ledger — UC-31 | M1 |

---

# 2. Sequence diagrams

## 2.1 UC-18 — Compute payroll run

Every branch below corresponds to a flow in the use case specification: A3 to the skipped line, E1 to the missing schedule, E3 and E4 to the net pay checks, E5 to the state guard, and E6 to the rollback.

```mermaid
sequenceDiagram
    autonumber
    actor PO as Payroll Officer
    participant UI as Payroll Run UI
    participant CTRL as PayrollRunController
    participant AUTH as AuthorizationService
    participant REPO as PayrollRepository
    participant EMP as EmployeeRepository
    participant ATT as AttendanceRepository
    participant ENG as ComputationEngine
    participant STAT as StatutoryScheduleService
    participant EXC as ExceptionEvaluator
    participant AUD as AuditService

    PO->>UI: Compute payroll run
    UI->>CTRL: computeRun runId
    activate CTRL

    CTRL->>AUTH: authorize user for COMPUTE_RUN
    AUTH-->>CTRL: permitted

    CTRL->>REPO: loadRun runId
    REPO-->>CTRL: run with payroll lines

    alt Run state is not Draft or Returned
        CTRL-->>UI: Refused - run is locked for editing
        UI-->>PO: Show refusal E5
    else Run is editable
        CTRL->>REPO: beginTransaction

        loop For each payroll line in the run
            CTRL->>EMP: getCompensationProfile empId, cutoffEnd
            alt No active compensation profile
                EMP-->>CTRL: none
                CTRL->>EXC: raise EX-02 blocking
                Note over CTRL,EXC: Line skipped, run continues A3, E2
            else Profile found
                EMP-->>CTRL: profile with rate version
                CTRL->>ATT: getAttendanceSummary empId, cutoff
                ATT-->>CTRL: hours, absences, late, undertime, leave, overtime

                CTRL->>ENG: computeBasicPay profile, attendance
                ENG-->>CTRL: basic pay per BR-05
                CTRL->>ENG: computeAdditionalPay overtime, night, holiday, allowances
                ENG-->>CTRL: additional pay per BR-15 to BR-17
                CTRL->>ENG: sumGrossPay
                ENG-->>CTRL: gross pay per BR-18
                CTRL->>ENG: computeAbsenceTardinessUndertime
                ENG-->>CTRL: time deductions per BR-11

                CTRL->>STAT: getScheduleInForce agency, payDate
                alt No schedule covers the pay date
                    STAT-->>CTRL: none
                    CTRL->>EXC: raise EX-05 blocking naming the agency
                    Note over CTRL,EXC: Line halted, remaining lines continue E1
                else Schedule in force
                    STAT-->>CTRL: schedule version
                    CTRL->>ENG: computeStatutory SSS, PhilHealth, PagIBIG
                    ENG-->>CTRL: employee and employer shares per BR-20
                    CTRL->>ENG: deriveTaxableCompensation
                    ENG-->>CTRL: taxable base per BR-19
                    CTRL->>ENG: computeWithholdingTax taxable base, frequency
                    ENG-->>CTRL: withholding tax
                end

                CTRL->>ENG: applyLoansAndOtherDeductions
                ENG-->>CTRL: deduction lines per BR-22, BR-23
                CTRL->>ENG: computeNetPay gross less total deductions
                ENG-->>CTRL: net pay per BR-13

                alt Total deductions exceed gross pay
                    CTRL->>EXC: raise EX-04 blocking
                end
                alt Net pay is zero, negative, or below floor
                    CTRL->>EXC: raise EX-03 blocking
                    Note over CTRL,EXC: Statutory deductions may not be reduced BR-25
                end

                CTRL->>REPO: savePayrollLine with rate and schedule versions
            end
        end

        CTRL->>REPO: computeRunTotals
        REPO-->>CTRL: total gross, totals by deduction, total net

        CTRL->>EXC: evaluateAllRules run
        EXC-->>CTRL: exception report by severity

        CTRL->>AUD: recordComputation user, timestamp, counts
        AUD-->>CTRL: audit entry written

        alt Any step failed
            CTRL->>REPO: rollbackTransaction
            CTRL-->>UI: Computation failed - no partial result E6
            UI-->>PO: Show failure
        else All steps succeeded
            CTRL->>REPO: commitTransaction
            CTRL-->>UI: computed, skipped, exceptions, elapsed time
            UI-->>PO: Show summary and exception report
        end
    end
    deactivate CTRL
```

**Figure 1.** *Sequence — UC-18 Compute payroll run*

**Reading the diagram.** Three things carry the design.

The **order is not incidental.** Withholding tax is computed at steps 30–33, after the mandatory contributions at step 28, because taxable compensation is gross less those contributions (BR-19). Reversing the two would produce a wrong tax on every payslip — and it is exactly the kind of ordering a spreadsheet leaves to whoever built the tab.

The **schedule version is captured, not just the amount.** At `savePayrollLine`, the rate version and statutory schedule version are stored on the line. This is what makes a finalized run reproducible after the schedule is superseded (C-04) and what allows UC-28 to reissue an identical payslip years later.

The **transaction spans the whole run.** A failure at any line rolls back everything (E6). A payroll is never left half-computed, which is the failure a spreadsheet cannot prevent when someone closes it midway.

---

## 2.2 UC-24 — Approve or return payroll run

```mermaid
sequenceDiagram
    autonumber
    actor AP as Approver
    participant UI as Payroll Run UI
    participant CTRL as PayrollRunController
    participant AUTH as AuthorizationService
    participant REPO as PayrollRepository
    participant EXC as ExceptionEvaluator
    participant AUD as AuditService
    participant NOTIF as NotificationService
    actor PO as Payroll Officer

    AP->>UI: Open a run awaiting review, or an approved run not yet finalized
    UI->>CTRL: getRunForReview runId
    activate CTRL
    CTRL->>AUTH: authorize user for REVIEW_RUN
    AUTH-->>CTRL: permitted
    CTRL->>REPO: loadRun with payroll lines and totals
    REPO-->>CTRL: payroll register
    CTRL->>EXC: getExceptionReport runId
    EXC-->>CTRL: acknowledged warnings, no blocking exceptions
    CTRL->>REPO: loadPriorPeriod for comparison
    REPO-->>CTRL: prior period figures
    CTRL-->>UI: register, exceptions, period comparison
    deactivate CTRL
    UI-->>AP: Display register UC-21 and exceptions UC-20

    AP->>UI: Examine individual payroll lines

    alt Approver decides to approve
        AP->>UI: Approve run
        UI->>CTRL: approve runId, userId
        activate CTRL
        CTRL->>REPO: getRunState runId
        REPO-->>CTRL: For Review

        alt State is not For Review
            CTRL-->>UI: Refused - only a run awaiting review may be approved
            UI-->>AP: Show refusal
        else State is For Review
            CTRL->>AUTH: checkSeparationOfDuty submitterId, userId
            alt Approver is the submitter
                AUTH-->>CTRL: refused per BR-28
                CTRL-->>UI: Refused - the preparer of a run may not approve it E1
                UI-->>AP: Show refusal
            else Approver differs from submitter
                AUTH-->>CTRL: permitted
                CTRL->>REPO: transition For Review to Approved
                CTRL->>REPO: recordTransition user, timestamp
                CTRL->>AUD: recordApproval user, timestamp
                CTRL->>NOTIF: notifyPayrollOfficer run approved
                NOTIF--)PO: Run approved
                CTRL-->>UI: Run approved
                UI-->>AP: Confirm approval
            end
        end
        deactivate CTRL

    else Approver decides to return
        AP->>UI: Return run with reason
        alt Reason is empty
            UI-->>AP: Refused - a reason is required E2
        else Reason provided
            UI->>CTRL: returnRun runId, userId, reason
            activate CTRL
            CTRL->>REPO: getRunState runId
            REPO-->>CTRL: For Review, or Approved
            alt State is Finalized
                CTRL-->>UI: Refused - reversal UC-26 is the only path E3
                UI-->>AP: Show refusal
            else State is For Review A1, or Approved A2
                CTRL->>REPO: transition to Returned
                CTRL->>REPO: clearApprovalFields approved_by, approved_at
                CTRL->>REPO: reopenEditingOfInputsAndAdjustments Returned to Draft
                CTRL->>REPO: recordTransition user, timestamp, reason
                CTRL->>AUD: recordReturn user, timestamp, reason
                CTRL->>NOTIF: notifyPayrollOfficer with reason
                NOTIF--)PO: Run returned with reason
                CTRL-->>UI: Run returned
                UI-->>AP: Confirm return
            end
            deactivate CTRL
        end
    end
```

**Figure 2.** *Sequence — UC-24 Approve or return payroll run*

**Reading the diagram.** The `checkSeparationOfDuty` call at step 20 is the entire control that BR-28 describes, and it is worth pointing at directly during a defense. In the client's current process this control does not exist: the same person prepares the worksheet, checks it, and passes it on, and nothing but habit prevents the check from being skipped. Here the refusal is structural — the system compares the submitter's account to the approver's and refuses when they match, whatever the user's intent.

Note also that a return is not a rejection of data but a state transition carrying a reason, recorded and notified. The client's current loop — steps H → I → F on their flowchart — leaves no trace of who sent work back or why.

**One return handler, two entry states.** The return branch is entered from `For Review` (UC-24 A1) and from `Approved` (UC-24 A2), and it does the same thing in both: the run moves to `Returned`, and `Returned` reopens to `Draft`. There is no direct `For Review → Draft` path — a run sent back is a returned run, whichever state it was sent back from, which is the rule FRS FR-4.4 states and Figure 7 draws.

The `clearApprovalFields` call is the one step whose effect differs between the two. Returning from `Approved` withdraws a signature, and the run must stop displaying an approver or the register would show an approval that no longer stands; returning from `For Review` clears columns that were never populated, and the call does nothing. It is written unconditionally rather than guarded because the handler is shared and the outcome — no approver on a run that is open for editing — must hold on both paths.

Nothing is lost by clearing. The approval, the return, both acting users, and the return reason are already rows in `RUN_TRANSITION`, which is append-only. The run record answers *what is true now*; the transition history answers *what happened*. Keeping those two questions in separate tables is what lets the first be cleared without losing the second.

---

## 2.3 UC-25 — Finalize payroll run

```mermaid
sequenceDiagram
    autonumber
    actor AP as Approver
    participant UI as Payroll Run UI
    participant CTRL as PayrollRunController
    participant AUTH as AuthorizationService
    participant REPO as PayrollRepository
    participant AUD as AuditService
    participant PAY as PayslipService
    participant ANC as LedgerAnchorService
    participant LED as Permissioned ledger

    AP->>UI: Finalize run
    UI->>CTRL: getFinalizationSummary runId
    activate CTRL
    CTRL->>REPO: loadRunTotals runId
    REPO-->>CTRL: period, headcount, total net pay
    CTRL-->>UI: finalization summary
    deactivate CTRL
    UI-->>AP: Confirm - period, headcount, total net, warning that finalization is not ordinarily reversible

    alt Approver cancels
        AP->>UI: Cancel
        UI-->>AP: No change made
    else Approver confirms
        AP->>UI: Confirm finalization
        UI->>CTRL: finalize runId, userId
        activate CTRL
        CTRL->>AUTH: authorize user for FINALIZE_RUN
        AUTH-->>CTRL: permitted
        CTRL->>REPO: getRunState runId
        REPO-->>CTRL: current state

        alt State is not Approved
            CTRL-->>UI: Refused - only an approved run may be finalized E1
            UI-->>AP: Show refusal
        else State is Approved
            CTRL->>REPO: beginTransaction
            CTRL->>REPO: bindRateVersionsToEachLine
            CTRL->>REPO: bindStatutoryScheduleVersionsToEachLine
            Note over CTRL,REPO: Preserves reproducibility after schedules change C-04
            CTRL->>REPO: setRunState Finalized
            CTRL->>REPO: markLinesImmutable
            CTRL->>REPO: recordTransition user, timestamp
            CTRL->>AUD: recordFinalization user, timestamp, total net
            CTRL->>ANC: queueAnchor runId, computed hash - UC-I6
            Note over CTRL,ANC: Queued inside the transaction so a committed run always has a pending anchor
            CTRL->>REPO: commitTransaction
            CTRL->>PAY: enablePayslipGeneration runId
            PAY-->>CTRL: enabled
            CTRL-->>UI: Run finalized
            UI-->>AP: Confirm - payslips may now be issued
            ANC--)LED: transmit hash - after commit, never blocking
            LED--)ANC: ledger reference and confirmation
            Note over ANC,LED: If the ledger is down the anchor stays pending and is retried. Finalization already succeeded - AC-6.3.5
        end
        deactivate CTRL
    end

    opt Any later edit attempted on the finalized run
        AP->>UI: Edit, delete, or recompute
        UI->>CTRL: mutate runId
        CTRL-->>UI: Refused - finalized runs are immutable E2
        Note over CTRL: No override exists for any role, Administrator included
    end
```

**Figure 3.** *Sequence — UC-25 Finalize payroll run*

**Reading the diagram.** The `bindRateVersionsToEachLine` and `bindStatutoryScheduleVersionsToEachLine` calls are the ones to explain. Binding the rate and statutory schedule versions to each line at the moment of finalization is what separates this system from the client's worksheets. When SSS publishes a new contribution table next year, every finalized run keeps showing the figures it was computed with, and a payslip reprinted in 2029 for a 2026 period reproduces the 2026 amounts exactly. A spreadsheet whose formula references a contribution table silently reports different numbers the moment that table is edited.

The trailing `opt` fragment is not a flow the user takes — it documents that the refusal has no override. Panels ask who can bypass it. The answer is nobody, and the diagram says so.

---

# 3. Activity diagrams

## 3.1 UC-18 — Compute payroll run

```mermaid
flowchart TD
    START([Start]) --> A1

    subgraph LANE_PO["Payroll Officer"]
        A1["Trigger computation
        for the payroll run"]
        A9["Review summary and
        exception report"]
    end

    subgraph LANE_SYS["System - PayrollRunController and ComputationEngine"]
        B1{"Run state is
        Draft or Returned?"}
        B2["Refuse - run is locked
        for editing"]
        B3["Begin transaction"]
        B29{"Recompute the whole run,
        or only stale lines?"}
        B30["Select the set of lines
        marked is_stale"]
        B31["Select every line
        in the run"]
        B4["Select next payroll line
        from the selected set"]
        B5{"Active compensation
        profile exists?"}
        B6["Raise EX-02 blocking
        and skip this line"]
        B7["Compute basic pay
        from attendance and rate"]
        B8["Compute overtime, night differential,
        holiday premium, allowances"]
        B9["Sum gross pay"]
        B10["Compute absence, tardiness,
        and undertime deductions"]
        B11{"Statutory schedule
        in force for pay date?"}
        B12["Raise EX-05 blocking
        and halt this line"]
        B13["Compute SSS, PhilHealth,
        and Pag-IBIG"]
        B14["Derive taxable compensation
        gross less non-taxable
        less contributions"]
        B15["Compute withholding tax"]
        B16["Apply loans, standing deductions,
        and adjustments"]
        B17["Total deductions,
        then compute net pay"]
        B18{"Net pay at or
        below floor?"}
        B19["Raise EX-03 blocking"]
        B20["Save payroll line with
        rate and schedule versions"]
        B21{"More payroll
        lines?"}
        B22["Compute run totals"]
        B23["Evaluate exception rules
        EX-01 to EX-10"]
        B24["Write audit entry"]
        B25{"Any step
        failed?"}
        B26["Roll back the entire
        computation"]
        B27["Commit transaction"]
        B28["Return computed, skipped,
        exceptions, elapsed time"]
    end

    A1 --> B1
    B1 -->|No| B2
    B2 --> STOP1([Stop - E5])
    B1 -->|Yes| B3
    B3 --> B29
    B29 -->|"stale only - A2"| B30
    B29 -->|"whole run - A1"| B31
    B30 --> B4
    B31 --> B4
    B4 --> B5
    B5 -->|No| B6
    B6 --> B21
    B5 -->|Yes| B7
    B7 --> B8 --> B9 --> B10 --> B11
    B11 -->|No| B12
    B12 --> B21
    B11 -->|Yes| B13
    B13 --> B14 --> B15 --> B16 --> B17 --> B18
    B18 -->|Yes| B19
    B19 --> B20
    B18 -->|No| B20
    B20 --> B21
    B21 -->|Yes| B4
    B21 -->|No| B22
    B22 --> B23 --> B24 --> B25
    B25 -->|Yes| B26
    B26 --> STOP2([Stop - E6])
    B25 -->|No| B27
    B27 --> B28 --> A9
    A9 --> STOP3([Stop])

    classDef act fill:#eef3f1,stroke:#0F6154,stroke-width:1px,color:#111;
    classDef dec fill:#f6f1e8,stroke:#8A5A12,stroke-width:1px,color:#111;
    classDef term fill:#ffffff,stroke:#333,stroke-width:1px,color:#111;
    class A1,A9,B2,B3,B4,B6,B7,B8,B9,B10,B12,B13,B14,B15,B16,B17,B19,B20,B22,B23,B24,B26,B27,B28,B30,B31 act;
    class B1,B5,B11,B18,B21,B25,B29 dec;
    class START,STOP1,STOP2,STOP3 term;
```

**Figure 4.** *Activity — UC-18 Compute payroll run*

**Reading the diagram.** The loop from `B21` back to `B4` is the whole answer to problem P2. In the client's process this loop is a human moving down a worksheet row by row, applying formulas by hand; here it is one action that runs to completion or rolls back entirely.

The two skip paths — `B6` for a missing compensation profile and `B12` for a missing statutory schedule — deliberately return to the loop rather than aborting the run. One employee's missing data does not stop the other ninety-nine from computing. The blocking exceptions they raise prevent submission until resolved, which is the right place for the stop: at the gate to review, not partway through the arithmetic.

---

## 3.2 UC-24 — Approve or return payroll run

```mermaid
flowchart TD
    START([Start - run is For Review]) --> C1

    subgraph LANE_AP["Approver"]
        C1["Open the run
        awaiting review"]
        C3["Review payroll register,
        exceptions, and period comparison"]
        C4{"Decision"}
        C5["Approve"]
        C6["Return with reason
        from For Review A1
        or from Approved A2"]
    end

    subgraph LANE_SYS["System"]
        C2["Load register, exception report,
        and prior period comparison"]
        D1{"Run state is
        For Review?"}
        D2["Refuse - run is not
        awaiting review"]
        D3{"Approver is the
        submitter?"}
        D4["Refuse - the preparer of a run
        may not approve it"]
        D5["Transition to Approved"]
        D6["Record transition
        with user and timestamp"]
        D7["Write audit entry"]
        D8["Notify Payroll Officer"]
        E1{"Reason
        provided?"}
        E2["Refuse - a reason
        is required"]
        E7{"Run already
        finalized?"}
        E8["Refuse - reversal UC-26
        is the only path"]
        E3["Transition to Returned,
        clear approved_by and approved_at,
        reopen editing to Draft"]
        E4["Record transition with
        user, timestamp, and reason"]
        E5["Write audit entry"]
        E6["Notify Payroll Officer
        with the reason"]
    end

    subgraph LANE_PO["Payroll Officer"]
        F1["Receive approval notice"]
        F2["Receive return notice
        and correct the run"]
    end

    C1 --> C2 --> C3 --> C4
    C4 -->|Approve| C5
    C4 -->|Return| C6

    C5 --> D1
    D1 -->|No| D2
    D2 --> STOP1([Stop - E3])
    D1 -->|Yes| D3
    D3 -->|Yes| D4
    D4 --> STOP2([Stop - E1])
    D3 -->|No| D5
    D5 --> D6 --> D7 --> D8 --> F1
    F1 --> STOP3([Stop - ready to finalize])

    C6 --> E1
    E1 -->|No| E2
    E2 --> C3
    E1 -->|Yes| E7
    E7 -->|Yes| E8
    E8 --> STOP5([Stop - E3])
    E7 -->|No| E3
    E3 --> E4 --> E5 --> E6 --> F2
    F2 --> STOP4([Stop - returns to UC-22])

    classDef act fill:#eef3f1,stroke:#0F6154,stroke-width:1px,color:#111;
    classDef dec fill:#f6f1e8,stroke:#8A5A12,stroke-width:1px,color:#111;
    classDef term fill:#ffffff,stroke:#333,stroke-width:1px,color:#111;
    class C1,C2,C3,C5,C6,D2,D4,D5,D6,D7,D8,E2,E3,E4,E5,E6,E8,F1,F2 act;
    class C4,D1,D3,E1,E7 dec;
    class START,STOP1,STOP2,STOP3,STOP4,STOP5 term;
```

**Figure 5.** *Activity — UC-24 Approve or return payroll run*

**Reading the diagram.** The three lanes are the point. Work crosses from Payroll Officer to Approver and back, and every crossing is a recorded transition rather than a verbal handoff. Decision `D3` sits in the System lane, not the Approver's — the separation of duty is not a policy the Approver is asked to observe but a check the system performs.

`E3` clears the approval fields in the same step that reopens editing, so the two never disagree: a run that can be edited is never a run that shows an approver. `E4` writes the transition immediately after, preserving the approval that was withdrawn. `E7` is the guard that separates a return from a reversal — once a run is finalized, sending it back is no longer a return at all, and UC-26 takes over with its own record and its own reason.

---

## 3.3 UC-25 — Finalize payroll run, with the UC-26 reversal branch

```mermaid
flowchart TD
    START([Start - run is Approved]) --> G1

    subgraph LANE_AP["Approver"]
        G1["Select finalize"]
        G3{"Confirm?"}
        G4["Confirm finalization"]
        G5["Cancel"]
        R1["Discover a material error
        after finalization"]
        R2["Select reverse"]
        R4["Enter reason
        and confirm"]
    end

    subgraph LANE_SYS["System"]
        G2["Present period, headcount,
        total net, and irreversibility warning"]
        H1{"Run state is
        Approved?"}
        H2["Refuse - only an approved
        run may be finalized"]
        H3["Bind rate and statutory
        schedule versions to each line"]
        H4["Set state Finalized
        and mark lines immutable"]
        H5["Record transition
        and write audit entry"]
        H6["Enable payslip generation
        and non-provisional reporting"]
        R3{"Payslips issued
        and pay date passed?"}
        R5["Refuse reversal - direct to
        retroactive adjustment"]
        R9{"A later period
        already finalized?"}
        R10["Warn that reversal breaks
        the period sequence;
        require acknowledgement"]
        R11{"Reason
        provided?"}
        R12["Refuse - a reason
        is required"]
        R6["Create permanent reversal record
        holding the original figures"]
        R7["Return run to Draft
        and reopen editing"]
        R8["Record reversal with user,
        timestamp, and reason"]
    end

    G1 --> G2 --> G3
    G3 -->|No| G5
    G5 --> STOP0([Stop - no change])
    G3 -->|Yes| G4
    G4 --> H1
    H1 -->|No| H2
    H2 --> STOP1([Stop - E1])
    H1 -->|Yes| H3
    H3 --> H4 --> H5 --> H6
    H6 --> STOP2([Stop - payslips may be issued, UC-27])

    H6 -.->|"error found later"| R1
    R1 --> R2 --> R3
    R3 -->|Yes| R5
    R5 --> STOP3([Stop - encode retroactive adjustment, UC-19])
    R3 -->|No| R9
    R9 -->|Yes| R10
    R10 --> R4
    R9 -->|No| R4
    R4 --> R11
    R11 -->|No| R12
    R12 --> STOP5([Stop - E2])
    R11 -->|Yes| R6
    R6 --> R7 --> R8
    R8 --> STOP4([Stop - run reopened, returns to UC-22])

    classDef act fill:#eef3f1,stroke:#0F6154,stroke-width:1px,color:#111;
    classDef dec fill:#f6f1e8,stroke:#8A5A12,stroke-width:1px,color:#111;
    classDef term fill:#ffffff,stroke:#333,stroke-width:1px,color:#111;
    classDef rev fill:#f4eaea,stroke:#8A2B2B,stroke-width:1px,color:#111;
    class G1,G2,G4,G5,H2,H3,H4,H5,H6 act;
    class R1,R2,R4,R5,R6,R7,R8,R10,R12 rev;
    class G3,H1,R3,R9,R11 dec;
    class START,STOP0,STOP1,STOP2,STOP3,STOP4,STOP5 term;
```

**Figure 6.** *Activity — UC-25 Finalize payroll run, with the UC-26 reversal branch*

**Reading the diagram.** The reversal branch is drawn in a different tone because it is the exceptional path, entered only when a finalized run proves wrong. Decision `R3` is the gate that matters: once payslips are issued and the pay date has passed, reversal is refused and the only correction is a retroactive adjustment in the open period (BR-24). This is deliberate. Rewriting a period whose payslips employees already hold would make the payslip and the record disagree — precisely the inconsistency problem P3 describes.

Note that `R6` creates a reversal record holding the original figures. Nothing is erased. A reversed run leaves more evidence than an unreversed one.

---

## 2.4 UC-31 — Verify payroll record integrity

```mermaid
sequenceDiagram
    autonumber
    actor AD as Administrator
    participant UI as Integrity Screens
    participant CTRL as IntegrityController
    participant AUTH as AuthorizationService
    participant VER as IntegrityVerificationService
    participant REPO as PayrollRepository
    participant LED as Permissioned ledger
    participant AUD as AuditService

    AD->>UI: Select a finalized run to verify
    UI->>CTRL: verify scopeType, recordId
    activate CTRL
    CTRL->>AUTH: authorize VERIFY_INTEGRITY
    AUTH-->>CTRL: permitted
    CTRL->>VER: verify scopeType, recordId
    activate VER

    VER->>REPO: loadRecordContent runId
    REPO-->>VER: totals, payroll lines, bound versions
    VER->>VER: recomputeHash using the anchored rule
    VER->>REPO: loadAnchor runId
    REPO-->>VER: payload hash, ledger reference, anchor status

    alt Anchor status is PENDING
        VER-->>CTRL: Not yet anchored - age of the queued anchor E2
    else Anchor is CONFIRMED
        VER->>LED: readAnchoredHash ledger reference
        alt Ledger unreachable
            LED--xVER: no response
            VER-->>CTRL: Verification unavailable - not a mismatch E3
        else Hash returned
            LED-->>VER: anchored hash
            alt Recomputed hash equals anchored hash
                VER-->>CTRL: MATCH - record unaltered since anchoring
            else Hashes differ
                VER-->>CTRL: MISMATCH - naming the record and its anchor time E1
                Note over VER,CTRL: Never re-anchored and never resolved automatically. Re-anchoring would destroy the only evidence
            end
        end
    end
    deactivate VER

    CTRL->>REPO: persistVerificationResult outcome, recomputed hash
    CTRL->>AUD: recordVerification user, timestamp, outcome
    CTRL-->>UI: outcome, both hashes, ledger reference
    deactivate CTRL
    UI-->>AD: Display result and offer PDF export A3
```

**Figure 8.** *Sequence — UC-31 Verify payroll record integrity*

**Reading the diagram.** Three outcomes, deliberately distinct. `MATCH` says the record is byte-for-byte what it was when anchored. `MISMATCH` says it is not. **Neither of the two failure branches says `MISMATCH` when it does not mean it** — a pending anchor (E2) and an unreachable ledger (E3) are absence of evidence, not evidence of alteration, and a system that conflated them would cry wolf during every ledger restart.

The `persistVerificationResult` call runs on every path, including the successful one. A verification history that recorded only failures could not answer the first question an auditor asks, which is not *"did it fail?"* but *"when was this last checked?"*

Note what the diagram does **not** contain: any write to the payroll record. Verification is read-only in every branch, which is why the FR-6.2 matrix can grant it to the Approver and the Viewer without widening anyone's ability to change payroll.

---

# 4. Payroll run state machine

UC-18, UC-24, and UC-25 above are transitions in one lifecycle. This diagram is the constraint they all obey, and it is the companion figure to the transition table in FRS §FR-4.4. UC-31 is not a transition — verification reads a run without moving it, which is why it appears nowhere on this diagram.

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Draft : UC-17 create run

    Draft --> Draft : UC-18 compute / UC-22 correct and recompute
    Draft --> ForReview : UC-23 submit<br/>guard - computed, no stale line,<br/>no blocking exception
    Draft --> Cancelled : UC-17 A2 cancel<br/>guard - state is Draft,<br/>never approved,<br/>confirmed per NFR-6.3

    ForReview --> Approved : UC-24 approve<br/>guard - approver is not the submitter
    ForReview --> Returned : UC-24 return<br/>reason required

    Approved --> Returned : UC-24 return before finalization<br/>reason required
    Approved --> Finalized : UC-25 finalize

    Returned --> Draft : editing reopened

    Finalized --> Draft : UC-26 reverse<br/>guard - payslips not issued<br/>or pay date not passed<br/>reversal record created

    Finalized --> [*] : period closed

    Cancelled --> [*]

    note right of Finalized
        Immutable. No edit, delete,
        or recompute by any role.
        Rate and schedule versions
        bound for reproducibility.
    end note

    note right of Draft
        The only state in which inputs
        and adjustments may be edited.
        Returned resolves here.
    end note
```

**Figure 7.** *State machine — payroll run lifecycle*

**Reading the diagram.** Every guard on this diagram is a control that the client's current process lacks entirely, because a worksheet has no states. The three that matter most:

- **Draft → For Review** requires no stale line and no unresolved blocking exception. Work cannot reach a reviewer half-finished.
- **For Review → Approved** requires that the approver differ from the submitter (BR-28).
- **Both return arrows land in `Returned`**, never directly in `Draft`. A run a reviewer sent back carries its reason and is visibly distinct from a draft that was never submitted; `Returned → Draft` is the reopening, not the return.
- **Finalized → Draft** is guarded by whether payslips have gone out, and always leaves a reversal record.

`Finalized` has exactly one outgoing transition other than closure, and it is guarded and evidenced. That is the formal statement of FR-4.5.

**On `Cancelled`.** The transition into it is specified as UC-17 alternate flow A2, not as a use case of its own, because abandoning a draft is a variation on creating one — the same actor, the same screen, the opposite outcome. Its guard is triple: the run must be in `Draft`, must never have been approved, and the Payroll Officer must confirm (NFR-6.3). Nothing enters `Cancelled` from any other state; a run that has reached an approver is returned (UC-24) or reversed (UC-26) instead, so that no work an approver has seen can disappear without a return reason. `Cancelled` is terminal and the row is retained — the constraint in data model §5.1 excludes cancelled runs from the uniqueness of period, population, and run type, which is what frees the period for a replacement run while the abandoned attempt and its reason stay in `RUN_TRANSITION`.

---

# 5. Traceability

**Table 1.** *Diagram coverage*

| Figure | Type | Use case | Flows represented | FRS trace |
|---|---|---|---|---|
| 1 | Sequence | UC-18 | Main, A3, E1, E2, E5, E6 | FR-2.1, 2.2, 2.3, 2.5, 2.6 |
| 2 | Sequence | UC-24 | Main, A1, A2, E1, E2, E3 | FR-4.4 (including the clearing of `approved_by` and `approved_at` on return) |
| 3 | Sequence | UC-25, UC-I6 | Main, E1, E2, and the anchor queued at finalization | FR-4.4, FR-4.5, FR-6.3 |
| 4 | Activity | UC-18 | Main, A1, A2, A3, E1, E2, E3, E4, E5, E6 | FR-2.1 – 2.6, FR-4.1, FR-4.3 |
| 5 | Activity | UC-24 | Main, A1, A2, E1, E2, E3 | FR-4.4, FR-6.2 |
| 6 | Activity | UC-25, UC-26 | UC-25 main, E1, E2; UC-26 main, A1, E1, E2, E3 | FR-4.5 |
| 7 | State machine | UC-17 (main and A2), 18, 22, 23, 24, 25, 26 | All legal transitions and their guards, including cancellation and both return paths | FR-4.4, FR-4.5 |
| 8 | Sequence | UC-31, UC-I6 | Main, A3, E1, E2, E3 | FR-6.3, FR-6.1 |

**Coverage.** The figures above model **twenty-four of the twenty-eight** alternate and exception flows defined by UC-18, UC-24, UC-25, UC-26, and UC-31. UC-18, UC-24, and UC-26 are complete. Four flows are not drawn:

| Not drawn | Why |
|---|---|
| UC-25 **A1** — finalize by department | The main flow, executed once per departmental run. Every step, guard, and record is identical; only the number of runs differs. A diagram would reproduce Figure 3 with a loop around it and say nothing Figure 3 does not already say |
| UC-31 **A1** — verify the audit chain | The same three outcomes as Figure 8, over a different input. The chain walk is a loop around one comparison; the comparison is what Figure 8 already draws |
| UC-31 **A2** — verify an entire period | Figure 8 executed once per run, with the results summarized. No new branch |
| UC-31 **E4** — record not found | A single refusal with no branching, and the specification states its one subtlety in a sentence: an anchor whose record has vanished is reported as a mismatch, not as a missing record |

Every flow that *branches* — every alternate that does something different and every exception that refuses — is drawn. The four omissions either repeat a drawn flow over different input or are single-step refusals fully stated in the use case.

Where a flow appears in more than one figure, the branch conditions are identical: Figures 2 and 5 model the same five UC-24 flows, and Figures 1 and 4 agree on every UC-18 branch they share. Figure 4 additionally draws E3, E4, and the `B29` line-selection choice that separates A1 (recompute the whole run) from A2 (recompute only stale lines) — the branch that realizes FR-4.3 and the `is_stale` column, and the one the sequence view has no natural place for.

**Business rules made visible.** BR-13 (computation order), BR-19 (taxable compensation base), BR-20 (employer shares), BR-22 and BR-23 (standing deductions and loans), BR-24 (reversal versus retroactive adjustment), BR-25 (net pay floor), BR-26 (audit in the same transaction), and BR-28 (separation of duty) all appear as concrete steps or guards rather than as prose the reader must take on trust.

---

# 6. Notes for Chapter III

1. **Figures 1–8 need vector redraws** at the department's required format. Content is authoritative; the redraw is formatting only. The activity diagrams should use proper UML swimlanes — the lanes here are a Mermaid approximation.

2. **Pair each sequence diagram with its activity diagram.** They answer different questions and a panel will ask both. The sequence diagram shows the components; the activity diagram shows who does what and where the flow branches.

3. **Figure 1 is the centrepiece.** It contains the answer to problem P2 in a single view: the computation order, the statutory application, the version capture, and the all-or-nothing transaction. If the defense allows one diagram on screen while explaining why Excel had to go, use this one.

4. **Figure 7 is the cheapest to explain and the hardest to argue with.** A worksheet has no states, therefore no guards, therefore no enforceable separation of duty and no period lock. The state machine makes that contrast structural rather than rhetorical.

5. **Two of the three remaining design artifacts now exist.** The entity relationship diagram is the [data model](./data-model.md), and the system architecture is the [system architecture](./system-architecture.md), whose §5 places the 14 participants of §1.4 into four layers alongside 21 further components the modelled use cases did not need. What remains is the class model — which follows from those 35 components and the 37 entities — and a data flow diagram if the department requires one.
