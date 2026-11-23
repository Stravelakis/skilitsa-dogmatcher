---
checkedAgainst: v2.0.0
checkedOn: 2026-11-21
---

## Start here

This page takes you from "I have never added anything to a website" to "my website has a quiz that suggests dog breeds, and I made it work myself". Go slowly. Every step says **what you should see**, so you always know if it worked. Nothing here can break your website if you follow the order, and you can stop and come back any day.

**What the quiz does:** a visitor answers questions about their life ("do you like long walks?", "will the dog be alone all day?"). The quiz compares the answers with lots of dog breeds and shows the three that fit best, with a short reason each, like a friend who knows dogs.

> **Do this first** Read **The ideas** below (about ten minutes). Then follow the steps in order. Come back to the rest when you need it.

**What you will have at the end:**

- The two add-ons installed and switched on.
- A list of breeds and questions loaded from a spreadsheet you control.
- A page on your website with the quiz on it.
- A result link you can open again later.

**What you need:**

- A WordPress website that you can log into as an administrator.
- A Google account (for the spreadsheet).
- One helper for **Step 2**, today: a developer, or whoever looks after your website's files. It is the one step that needs a tool you may not have.

**How long:** about an hour, plus waiting for your helper.

> **Watch out** There is no ready-made download yet (no release is published on GitHub as of 21 Nov 2026). That is why Step 2 needs a helper. If a ready-made zip exists by the time you read this, you can skip that helper.

## The ideas

Take them one at a time. Nothing here is a test.

### 1. A website is a set of pages, and WordPress makes them

**WordPress** is a free program that runs a website. You write pages in a screen in your web browser, and WordPress shows them to visitors. Many websites are built this way.

You get into the control room by going to your site's address plus `/wp-admin`, for example `yoursite.com/wp-admin`, and logging in. This control room is called **the admin**. In the admin there is a menu down the left side.

### 2. A plugin adds a new ability

A **plugin** is an add-on for WordPress, like an app you put on a phone. Without one, your site cannot do a thing. With one, it can. DogMatcher is **two plugins that work as a pair**:

- **Skilitsa Core** is the brain. It keeps the facts about dogs and works out the matches. Visitors never see it.
- **Skilitsa Matcher** is the face. It is the quiz that visitors click through. It cannot work without Core.

### 3. A trait is something a dog is like

A **trait** is one thing a dog can be like: how much energy it has, how much it barks, how much it sheds. The quiz works with a list of traits (the sample list has 24). Each breed has a number from 1 to 5 for every trait. For example, energy 5 means very energetic.

### 4. Questions are asked, traits are scored

A visitor does not see "traits". They see **questions** like "how much time will you spend walking?". Each answer, from 1 to 5, tells the quiz what the visitor wants for one or more traits.

### 5. A mismatch costs more one way than the other

Imagine you want a quiet dog. A quiet dog is fine, and a very loud dog is a problem. Wanting a calm dog and getting an extra-calm one costs you nothing. So the quiz counts "too much" and "too little" differently for each trait.

### 6. A "dealbreaker" is a must-have

On most questions the visitor can press a button that means "I definitely want this". That is a **dealbreaker**. The quiz then does two things: it counts that trait **double**, and it **leaves out** any breed that is too far from it. Left-out breeds are listed in a closed list with the reason, so nothing is hidden.

### 7. The facts live in a Google Sheet

A **Google Sheet** is a table on the internet, like a notebook many people can open. DogMatcher keeps the questions, the breeds and all the wording there, not inside WordPress. You change the sheet, then press a button and WordPress copies it in.

### 8. A breed needs its own page on your website

Each breed also needs a **Breed post**: a page of its own on your site, with a title, some text and a picture. The numbers from the sheet are attached to that page. This is the step people forget. A breed in the sheet with no page on your site is skipped, quietly.

How does the plugin know which sheet row goes with which page? By a short name in the page's web address, called the **slug**. The page `yoursite.com/breeds/border-collie/` has the slug `border-collie`, and the sheet row has `border_collie`. The plugin treats a dash and an underscore as the same, so these match.

### 9. A shortcode is a magic word in square brackets

To put the quiz on a page you either add a **block** (a building piece you click to add, like a picture) or you type a **shortcode**: `[skilitsa_matcher]`. Both do the same thing.

### 10. Same answers, same result

The quiz has no dice. If two people give exactly the same answers and your data has not changed, they get exactly the same three breeds. Only the exact wording of a reason can vary, and it is fixed for each breed.

### 11. Results get their own link

When someone finishes, the quiz saves the result and gives it a link that ends in 12 random letters and numbers, like `/match/k3x9a2b7q1zd/`. Anyone with the link can see that result. It works like a coat-check ticket: it is not secret, but nobody can guess yours. Results are deleted after 90 days.

### 12. What happens to a visitor's answers

The quiz in the visitor's browser talks only to **your own** website. Your site keeps the answers under that random result code. The plugin does not write down the visitor's IP address, their browser or any account. Your web host may keep its own logs, which the plugin does not control.

> **In short** WordPress runs your website. A plugin adds an ability. Two plugins make the quiz. The dog facts live in a Google Sheet, and each breed needs a page on your site. A visitor answers questions, and the quiz shows the three best breeds, the same way every time.

## Step 1: Check your website

1. Log into your admin (`yoursite.com/wp-admin`).
2. In the left menu click **Tools**, then **Site Health**, then the **Info** tab at the top.
3. Click **WordPress** and read the version. Then click **Server** and find the PHP version.

**What you should see:** WordPress **6.4 or higher**, and PHP **8.1 or higher**. If a number is lower, ask your web host to upgrade it. The plugins will not work on older versions.

## Step 2: Get the two add-ons ready

This is the step for your helper. Show them this paragraph.

> *Please prepare both plugins in the DogMatcher repository. Either run `bin/build-release.sh` (it needs Composer and Python, and it writes `build/skilitsa-core.zip` and `build/skilitsa-matcher.zip`), or run `composer install --no-dev --optimize-autoloader` inside each of `plugins/skilitsa-core` and `plugins/skilitsa-matcher`. The plugins will not start without the generated `vendor/autoload.php`.*

**Why a helper?** Each add-on needs a small loader file that is not stored with the code. A developer tool creates it.

**What you should end up with:** two files, `skilitsa-core.zip` and `skilitsa-matcher.zip`, or two finished folders.

> **Watch out** Do not upload the folders straight from GitHub without this step. WordPress would show a red message that says **"Skilitsa Core: dependencies are missing."** Nothing is broken; it just means this step was skipped.

## Step 3: Upload and switch them on

1. In your admin click **Plugins**, then **Add New**, then **Upload Plugin** at the top.
2. Click **Choose file**, pick `skilitsa-core.zip`, then click **Install Now**.
3. Click **Activate Plugin**.
4. Do the same with `skilitsa-matcher.zip`. Activate it **second**.

**What you should see:** a new item called **Skilitsa** in the left menu. On the **Plugins** page, next to **Deactivate** under Skilitsa Core, there is a **Settings** link.

If you activated Matcher first, you will see **"Skilitsa Matcher needs Skilitsa Core 2.0.0 or newer to be installed and active."** Activate Core, and the message goes away.

## Step 4: Look at your spreadsheet

The plugin comes pointed at the sheet it was built with. For your own quiz you want **your own copy**.

1. Open your Google Sheet.
2. Check the tabs along the bottom. There must be eight, spelled exactly like this: `SETTINGS`, `UI_TEXTS`, `SECTIONS`, `TRAITS`, `QUESTIONS`, `MAPPING`, `RESULT_TEMPLATES` and `BREEDS`.
3. Click the blue **Share** button, and under General access choose **Anyone with the link**, as **Viewer**.
4. Copy the address from your browser's address bar.

**What you should see:** eight tabs, and a share setting that says "Anyone with the link". Without that setting the plugin cannot read your sheet.

> **Why** The plugin asks Google for each tab by name, as a plain table. It does not log into your Google account. That is why the sheet must be viewable by link, and why the tab and column names must not be changed.

<details>
<summary>What is in each tab?</summary>

- **TRAITS:** the list of traits and their descriptions.
- **QUESTIONS:** the questions visitors see, with the words for the low end and the high end of the scale.
- **MAPPING:** which question tells you about which trait, and whether the answer is flipped.
- **BREEDS:** one row per breed, one 1-to-5 number per trait.
- **UI_TEXTS:** the button and message wording.
- **RESULT_TEMPLATES:** the sentences that explain a match.
- **SECTIONS** and **SETTINGS:** the groups of questions, and the sheet's own settings.
</details>

<details>
<summary>Do I have to write the quiz in Greek?</summary>

The sample data is in Greek. If you copy the sheet and translate the text columns, the quiz shows your language. Keep every tab name and every column name exactly as it is.
</details>

## Step 5: Tell the plugin about your sheet

1. In the admin click **Skilitsa**, then **Settings**.
2. Find **Source sheet**. Paste your sheet's address into the box.
3. Click **Save**.

**What you should see:** the box now shows a short code (the sheet's ID) instead of the whole address. The plugin picks the code out for you.

Every setting on this screen has its default written beside it and one sentence of help. You do not need to change any other for now.

## Step 6: Make a page for each breed

1. In the admin click **Skilitsa**, then **Breed posts**.
2. Click **Add New** at the top.
3. Type the breed's name as the title, and some text if you like.
4. In the settings panel on the right, find **Permalink** (or **URL**) and make the last part the breed's key from your sheet, for example `border-collie`.
5. Click **Publish**.
6. Repeat for each breed.

**What you should see:** each breed appears in the **Breed posts** list as **Published**.

> **Watch out** The slug must match the sheet's `breed_key` (a dash and an underscore count as the same). A page that is only a Draft is not used.

## Step 7: Bring the data in

1. Click **Skilitsa**, then **Import**.
2. Click **Fetch and preview**.
3. Read what it says it would change. Nothing is saved yet.
4. If there is an apply button, click **Apply this import**.

**What you should see:** a table with the columns **Table**, **Now**, **After** and **Change**, then (if there is something to apply) a button called **Apply this import**. After you click it, a green box says **Import applied.** with a count for each table. If nothing would change, the page says **Nothing would change. There is nothing to apply.**

> **Watch out** If you see red errors and **no** apply button, the plugin found a problem it will not import over. Usual causes: the sheet is not set to "Anyone with the link", or a tab or column name is spelled differently. Fix the sheet and click **Fetch and preview** again. Yellow warnings ("Worth knowing, but not blocking") do not stop you.

> **Why** The two steps (preview, then apply) are so that nothing changes by surprise. Apply is all-or-nothing: if anything goes wrong halfway, nothing is changed.

**Good to know:** import again any time you change the sheet. It **replaces** the questions, the wording and every breed value with what is in the sheet. The one thing it keeps is the penalty and weight numbers you changed by hand on the **Traits** screen. So always change the sheet, not the values on the Breed data screen.

## Step 8: Check the breeds arrived

1. Click **Skilitsa**, then **Breed data**.

**What you should see:** a list of your breeds, and for each, how many traits it has and which are missing. If a breed you made is not listed, or has no traits, its slug does not match the sheet. Go back to Step 6.

## Step 9: Put the quiz on a page

1. In the admin click **Pages**, then **Add New**.
2. Click the **+** button and search for **Skilitsa matcher**. Click it to add the block.
3. Click **Publish**, then **View Page**.

Another way: instead of the block, type `[skilitsa_matcher]` on its own line.

**What you should see:** on the live page, a first question, a progress bar, and a checkbox about the terms. In the sample data all the words are in Greek.

> **Watch out** Only one quiz block can sit on a page. If visitors have JavaScript turned off, they see a short message saying the quiz needs it.

## Step 10: Try the quiz yourself

1. Tick the terms checkbox.
2. Pick a number from 1 to 5. The two ends of the scale say what the low and high ends mean.
3. Click **Next**. Once the terms are ticked it stays dimmed until you pick an answer.
4. Keep going to the end. On most questions there are two small buttons: **I definitely want this** (in the sample data **Το θέλω οπωσδήποτε**) and **it makes no difference to me** (**Δεν μου κάνει διαφορά**). Try each on one question.
5. On the last question the button changes to see the results.

**What you should see:** a podium with three breeds and medals, a score for each, and an explanation that opens when you click it. More breeds follow in a row you can scroll. If you pressed a dealbreaker, a closed list at the bottom says how many breeds were left out.

6. Copy the address from your browser's address bar. It ends in `/match/` and 12 letters and numbers. Open it in a new tab.

**What you should see:** the same result again.

<details>
<summary>I see a message asking for more answers</summary>

You answered too few questions. The quiz needs at least **8 traits** worth of answers before it will show breeds. Answer more, and avoid pressing "makes no difference" on everything.
</details>

<details>
<summary>I see a note that the top three are close</summary>

That is on purpose. If the first and third are within 5 points, the page tells the visitor that all three fit about equally.
</details>

## Step 11: Make it yours

Back in **Skilitsa → Settings** you can change:

- **Accent colour:** the one colour the quiz uses for buttons and links. It must be a six-digit colour code like `#2f6f4f`.
- **Scoring strictness** and the other number settings. Lower strictness numbers make every score lower. Leave them alone unless you have a reason.
- **Traits** screen: how much a mismatch costs per trait. This is for fine tuning.

> **Try it** Change the accent colour, click **Save**, and reload your quiz page. The buttons should change colour. If you do not like it, type the old one back.

## Optional: AI drafts

The plugin can use an AI service to suggest things, such as breed trait values or picture descriptions. It is **off** until a developer adds a key to the file `wp-config.php`. It can only make **drafts** that you read and approve or reject in **Skilitsa → AI drafts**. Nothing it writes reaches a visitor on its own. You can ignore this completely. If you want it, your developer can follow [the AI setup notes](https://github.com/Stravelakis/skilitsa-dogmatcher/blob/HEAD/docs/ai-configuration.md).

## If something goes wrong

<details>
<summary>A red notice says "dependencies are missing"</summary>

Step 2 was skipped. Ask your helper to prepare the plugins, then upload them again.
</details>

<details>
<summary>The quiz box is empty</summary>

Check that the page really has the block or `[skilitsa_matcher]`. If the box says the quiz is not set up, you have no questions yet: do Step 7.
</details>

<details>
<summary>The import says it could not fetch a tab</summary>

The sheet is not shared as "Anyone with the link", or the tab is missing or misspelled. Fix it in Google Sheets and try again.
</details>

<details>
<summary>A breed never shows up in results</summary>

Open **Skilitsa → Breed data**. If it is missing or has no traits, its page is not published, or its slug does not match the sheet's breed key.
</details>

<details>
<summary>A result link shows "not found"</summary>

Either the link was copied wrongly, or the result is more than 90 days old and was removed. If every link fails on a brand-new install, go to **Settings → Permalinks** in WordPress and click **Save Changes**, which refreshes the site's address rules.
</details>

<details>
<summary>The page says too many requests</summary>

The quiz allows 60 requests a minute from one address. If a whole classroom sits behind one address, they can hit it together. Wait a minute.
</details>

<details>
<summary>I changed a number on the Breed data screen, and it vanished</summary>

The next import replaced it with the sheet's number. Change the sheet instead.
</details>

## A small dictionary

- **Admin:** the control room of your website, at `/wp-admin`.
- **Block:** a piece you add in the page editor.
- **Breed post:** a page on your site for one breed.
- **Dealbreaker:** a "must have" answer that can remove breeds.
- **Google Sheet:** an online table. It is where the quiz data lives.
- **Plugin:** an add-on for WordPress.
- **Shortcode:** a word in square brackets that shows something on a page.
- **Slug:** the last part of a page's web address.
- **Trait:** one thing a dog is like, scored from 1 to 5.
- **Result link:** the `/match/` address that shows one result again.
