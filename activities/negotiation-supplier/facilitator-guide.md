# Negotiating with an Algorithmic Supplier: Facilitator Guide

## Overview

Students act as a procurement manager at Brightline Robotics buying 5,000 TS-40 temperature sensors. Their counterpart, Dana Reyes of Northgate Components, is a **rule-based negotiating agent**: a small algorithm with hidden costs, a walk-away point and a concession strategy. Both companies are fictional.

Students make structured offers across five issues and can ask Dana six questions. Because Dana follows fixed rules, every student faces the same counterpart, so outcomes are directly comparable across the class. The exercise works for teaching negotiation, and also for discussing automated negotiation agents, which companies increasingly use in procurement.

The simulation runs entirely in the browser. It needs no AI service, sends nothing anywhere and stores nothing on the server.

## Learning objectives

Students will be able to:

1. Prepare for and run a multi-issue negotiation covering price, delivery, payment terms, warranty and contract length.
2. Create value by trading issues the parties value differently (logrolling).
3. Use questions to uncover the other side's interests and constraints.
4. Explain how anchoring, reciprocity and concession patterns shaped their result.
5. Describe how an algorithmic negotiator "thinks", and what that implies for negotiating with, or deploying, automated agents.

## Timing

| Segment | Minutes |
|---|---|
| Brief and preparation (the brief is on the simulation page) | 10 |
| Negotiation | 15–20 |
| Debrief | 30–45 |

For asynchronous use, assign it as homework. Have students upload their downloaded transcript, which includes their score, with a 300-word reflection.

## What students know

The brief is built into the simulation:
- List price is $42.00 per unit. Their budget target is $36.00; their boss authorized up to $38.00.
- They need delivery in 8 weeks or less for a product launch.
- Finance prefers net 60 payment terms.
- They forecast about 12,000 units a year for two years, not yet committed.
- They get up to 8 offers. Questions are free. They can accept Dana's latest offer or walk away at any time.

## Confidential: how Dana works

**How Dana values a package.** She starts from the price per unit, subtracts what each concession costs Northgate, and adds what a long-term deal is worth to her:

| Term | Effect on Dana's value per unit |
|---|---|
| 7-week rush delivery | −$1.50 |
| Net 45 / net 60 | −$0.30 / −$0.60 |
| 24-month warranty | −$0.40 |
| Two-year agreement (10,000+/yr) | **+$3.00** |

**When she accepts.** Her aspiration starts at $41.00 of value and falls each time the student makes an offer, by $1.00, then $0.70, $0.50, $0.35, $0.25, $0.20, $0.15 and $0.10. She never goes below $34.75. She accepts any offer worth at least her current aspiration. Otherwise she counters on the student's own terms, at the price that meets her aspiration.

**Reciprocity.** If a student's offer is no better for Dana than their previous one, she concedes only 30% of the scheduled amount and says she needs to see movement.

**Lowball offers.** An offer worth less than $30.75 to her draws a firm reply and no concession at all.

**Questions.** Each question reveals true information. Asking about timing reveals that her quarter closes soon. It also lowers her aspiration by $0.50, because a motivated seller gives more. If a student hasn't asked about priorities or volume by the third offer, Dana hints once that a longer commitment would help.

**The integrative solution.** Brightline values on-time delivery highly. Dana values a multi-year commitment highly, and Brightline's forecast makes that commitment low-risk. The best deals combine a two-year agreement, 7-week delivery and net 60 at about $36.25–36.85.

## Scoring

At the end, students see a **Brightline score**:

| Element | Points |
|---|---|
| Price | 10 per $1 under the $42.00 list price |
| Delivery | +20 at 7 weeks; −20 at 10 weeks (misses the launch) |
| Payment | net 45 +4; net 60 +8 |
| 24-month warranty | +4 |
| Two-year agreement | +3 |

Prices above $38.00 are flagged as exceeding the student's authority.

**Benchmark results** from test runs:

| Strategy | Typical deal | Score |
|---|---|---|
| Bargains on price only, standard terms | $37.75, 10 weeks, net 30 | about 22 |
| Asks for rush delivery, bargains on price only | $39.25, 7 weeks (above authority) | about 47 |
| Two-year agreement but accepts 10 weeks | about $35.45, 10 weeks | about 48 |
| Trades across issues, no questions | about $36.85, 7 weeks, net 60, 2 years | about 82 |
| Trades across issues and asks about timing | about $36.45, 7 weeks, net 60, 2 years | about 86 |
| Repeats the same low offer | about $39.35, 10 weeks | under 10 |
| Opens at $40 | Accepted immediately at $40.00 | 0 |

## Debrief questions

1. Collect each student's final terms and score on the board. Why do results vary so much against an identical counterpart?
2. What was your first offer? How did it anchor the rest of the negotiation? (Anyone who opened at $40 got an instant yes. What does that tell you?)
3. Which issues did you trade? Which did you never raise?
4. Which questions did you ask, and what did you learn? Did anyone ask about timing?
5. Dana conceded less when you didn't move. Did you notice? How is that like, or unlike, a human negotiator?
6. **Reverse-engineer Dana.** Based on her behavior, sketch the rules she follows. Then reveal the table above. How close were you?
7. **Algorithmic agents.** Companies now use software agents to negotiate routine procurement. What are the advantages of a rule-based agent (consistency, no fatigue, can't be charmed)? What are the risks: exploitable patterns, rigidity, revealing information through predictable behavior? If you were deploying Dana for Northgate, what would you change?
8. How would this negotiation differ against a generative AI counterpart, or a person?

## Notes for instructors

- Dana's numbers are deterministic. Only the wording of some replies varies, so results are comparable and the "reverse-engineer Dana" discussion works.
- Dana's parameters are in the page's code, so a technically minded student could read them with the browser's "view source". That's rare in practice, and you can turn it into part of the AI-literacy discussion: an agent's strategy is only as private as its code.
- Students who replay the simulation can learn Dana's rules. For graded use, ask for the first attempt's transcript, or run it in class.
- The simulation stores progress in the student's browser tab only. Closing the tab ends the session.
