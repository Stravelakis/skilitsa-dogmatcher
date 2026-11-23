// Per-repo config. Most projects only touch this file and src/docs/*.md.

interface SocialLink {
  kind: 'github' | 'linkedin';
  label: string;
  href: string;
}

export const siteConfig = {
  projectName: "Skilitsa DogMatcher",
  description: "A WordPress dog-breed matching quiz. Visitors answer questions; the plugin scores every breed against their answers and shows a podium. Breed data comes from a Google Sheet you control.",
  repoUrl: "https://github.com/Stravelakis/skilitsa-dogmatcher",
  // Path under /public including the base path, e.g. '/repo-name/favicon.svg'. Empty = no favicon tag.
  faviconHref: "/skilitsa-dogmatcher/favicon.svg",

  // What a link to the site shows when shared (Open Graph / Twitter cards).
  seoTitle: "Skilitsa DogMatcher: a dog-breed matching quiz for WordPress",
  socialImage: "og-image.png",
  socialImageAlt: "Skilitsa DogMatcher: a dog-breed matching quiz for WordPress.",
  locale: 'en_GB',

  // Left menu, top group. The "On this page" group below it is built from the
  // headings of the reading level on screen.
  nav: [
    { id: 'top', label: 'Overview' },
    { id: 'features', label: 'What it does' },
  ],

  // Buttons and chips in the hero.
  heroLinks: [
    { label: 'Install steps', href: '#install', primary: true },
    { label: 'Source on GitHub', href: 'https://github.com/Stravelakis/skilitsa-dogmatcher' },
    { label: 'AI setup notes', href: 'https://github.com/Stravelakis/skilitsa-dogmatcher/blob/HEAD/docs/ai-configuration.md' },
  ],
  heroPills: ['Two WordPress plugins', 'Your data in a Google Sheet', 'Same answers, same result', 'GPL-3.0-or-later'],

  // The three cards under the hero. They switch the reading level.
  levelCards: [
    { id: 'dev', tag: 'Dev', title: 'How it is built', text: 'The two plugins, the scoring maths, the REST routes, what is stored, what leaves the browser, tests and release.' },
    { id: 'plain', tag: 'English', title: 'How to use it', text: 'For site owners who do not write code. Install, the Google Sheet, the admin screens, privacy, fixes.' },
    { id: 'eli5', tag: 'ELI5', title: 'Start from zero', text: 'What WordPress and a plugin are, then every click spelled out, with what you should see.' },
  ],

  // First visit lands on English; a reader's saved choice still wins.
  defaultVernacular: 'plain',

  // Reading-level switch at the top of the sidebar. The ids are fixed (they
  // match src/docs/dev.md, plain.md, eli5.md); change labels only.
  vernaculars: [
    { id: 'dev', label: 'Dev' },
    { id: 'plain', label: 'English' },
    { id: 'eli5', label: 'ELI5' },
  ],

  // Tally form link (STANDARDS §8). Empty = no "Send feedback" link.
  feedbackUrl: '',

  playground: {
    // true only for JS/web projects, where running the code in the browser is real.
    enabled: false,
    starterCode: `// try it\nconsole.log("hello from the playground")`,
  },

  author: {
    name: 'Stravelakis',
    links: [
      { kind: 'github', label: 'GitHub', href: 'https://github.com/Stravelakis' },
      { kind: 'linkedin', label: 'LinkedIn', href: 'https://www.linkedin.com/in/stravelakiscom/' },
    ] as SocialLink[],
  },
};
