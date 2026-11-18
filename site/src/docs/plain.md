---
# Not reviewed yet: owner ticks templates/docs/REVIEW-CHECKLIST.md, then adds
# checkedAgainst: v2.0.0 and checkedOn: YYYY-MM-DD here.
---

## Skilitsa DogMatcher in plain English

It is a quiz for a WordPress website. A visitor answers questions about their life (how active they are, whether they have kids, how much time they have), and the plugin shows the three dog breeds that fit best, with a short reason for each.

- **Your own data.** The questions, the breeds and all the texts live in a Google Sheet you control. The plugin imports them, shows you what would change, and only then applies it.
- **Fair and explainable.** Each result says why a breed matched, and a "dealbreaker" answer excludes breeds and names the trait that conflicts.
- **Shareable.** Every result has its own link that keeps showing the same podium.
- **Careful with accessibility.** The quiz works with screen readers and the keyboard, and respects "reduce motion".
- **An admin you can use.** Settings, Traits, Breed data, Breed posts and Import, with help on every screen.
- **Optional AI helper.** It can suggest drafts to the site owner. A person approves every one. Nothing it writes goes to visitors by itself.

**Good to know:** the quiz and the original data are in Greek, built for skilitsa.com. The admin is in English. You will need your own sheet with your own breed data.
