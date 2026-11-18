/*
 * Skilitsa Matcher — quiz client.
 *
 * The browser never scores anything. It collects answers, posts them, and
 * renders what comes back. v1 shipped the entire 257-breed matrix to every
 * visitor; here the client sees only its own questions and its own results.
 *
 * Accessibility rules from spec section 9 that this file is responsible for:
 *   - explanation panels open on click, Enter, Space AND hover, and close on
 *     Escape. Never hover-only.
 *   - the scale is a real fieldset of real radios, so arrow keys work for free
 *   - results are announced through an aria-live region
 */
(function () {
  'use strict';

  const root =
    document.querySelector('[data-skilitsa-matcher]') ||
    document.querySelector('[data-skilitsa-matcher-result]');

  if (!root) {
    return;
  }

  // A stored result page mounts the same client, which fetches by token rather
  // than running the quiz. Rendering the result twice - once in PHP, once here -
  // would be two chances to disagree about what the user matched with.
  const storedToken = root.getAttribute('data-skilitsa-matcher-result');

  const config = window.SkilitsaMatcher || {};
  const api = String(config.restUrl || '').replace(/\/$/, '');

  const state = {
    questions: [],
    uiTexts: {},
    terms: { version: '', label: '' },
    termsAccepted: false,
    // The result the reader came back from, so Back on question one can
    // return to it instead of being a button that does nothing.
    lastResult: null,
    index: 0,
    answers: {},
    dealbreakers: {},
    ignored: {},
    token: null,
    busy: false,
  };

  /* ------------------------------------------------------------- utilities */

  const t = (key, fallback) => {
    const value = state.uiTexts[key];
    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
  };

  const el = (tag, attrs = {}, ...children) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([name, value]) => {
      if (value === null || value === undefined || value === false) {
        return;
      }
      if (name === 'class') {
        node.className = value;
      } else if (name === 'text') {
        node.textContent = value;
      } else if (name.startsWith('on') && typeof value === 'function') {
        node.addEventListener(name.slice(2).toLowerCase(), value);
      } else {
        node.setAttribute(name, value === true ? '' : String(value));
      }
    });
    children.flat().forEach((child) => {
      if (child === null || child === undefined) {
        return;
      }
      node.append(child.nodeType ? child : document.createTextNode(String(child)));
    });
    return node;
  };

  const post = async (path, body) => {
    const response = await fetch(api + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!response.ok) {
      throw new Error('request failed: ' + response.status);
    }
    return response.json();
  };

  const track = (type, target) => {
    if (!state.token) {
      return;
    }
    // Fire and forget: telemetry must never delay or break the interface.
    post('/match/event', { token: state.token, type: type, target: target || '' }).catch(() => {});
  };

  /* ------------------------------------------- explanation panel (a11y core) */

  /**
   * An explanation affordance that works for every input method.
   *
   * A `title` attribute would be invisible to touch and to screen readers, so
   * the content is a real element and the trigger is a real button.
   */
  const infoAffordance = (label, body) => {
    if (!body) {
      return null;
    }

    const panelId = 'sk-info-' + Math.random().toString(36).slice(2, 9);

    const panel = el('div', { class: 'sk-info-panel', id: panelId, hidden: true, text: body });

    const button = el('button', {
      type: 'button',
      class: 'sk-info',
      'aria-expanded': 'false',
      'aria-controls': panelId,
      'aria-label': label,
    });

    // The information symbol, as an image rather than a letter.
    //
    // Plain 'i' was a letter wherever it landed. The emoji failed too at
    // first, because a font renders it as a serif i - but WordPress swaps
    // emoji for Twemoji images, so this is the blue circle everywhere and
    // not a typeface's opinion of it.
    //
    // Was a lightbulb: 'i' is a letter wherever it lands, and on
    // Windows it read as a stray capital I beside the question.
    //
    // The glyph is decoration. Without aria-hidden it is absorbed into the
    // surrounding legend, which a screen reader then reads as
    // "...genika? i" - the button already carries a real label.
    button.append(el('span', { 'aria-hidden': 'true', text: '\u2139\uFE0F' }));

    const setOpen = (open) => {
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.hidden = !open;
    };

    button.addEventListener('click', () => setOpen(panel.hidden));

    // Hover is an addition for pointer users, never the only way in.
    button.addEventListener('mouseenter', () => setOpen(true));
    button.addEventListener('mouseleave', () => {
      if (document.activeElement !== button) {
        setOpen(false);
      }
    });

    button.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    });

    return { button: button, panel: panel };
  };

  /* --------------------------------------------------- reading preferences */

  const READING_KEY = 'skilitsa_reading_prefs';

  const applyReading = (prefs) => {
    root.style.setProperty('--sk-font-size', (prefs.size || 1.05) + 'rem');
    root.style.setProperty('--sk-line-height', prefs.spacing || 1.6);
  };

  /**
   * The reading preference still applies; the toolbar that set it does not.
   *
   * Three buttons above the first question competed with the question itself
   * for the reader's attention, which is the opposite of what an accessibility
   * affordance should do. The stored preference is still honoured for anyone
   * who set one, and the defaults are already the accessible ones: 1.05rem
   * type and 1.6 line height.
   */
  const readingControl = () => {
    let prefs = { size: 1.05, spacing: 1.6 };
    try {
      const stored = window.localStorage.getItem(READING_KEY);
      if (stored) {
        prefs = Object.assign(prefs, JSON.parse(stored));
      }
    } catch (e) {
      /* storage unavailable; defaults are fine */
    }
    applyReading(prefs);

    return null;
  };
  /**
   * Splits emoji out of a string so they can be sized as illustration.
   *
   * The scale ends carry an emoji each, and at body-text size they read as
   * punctuation rather than as the picture they are meant to be. Wrapping
   * them lets CSS treat them as the small icons they already are, without
   * anyone having to draw an icon set or ship an SVG sprite.
   */
  const EMOJI = /(\p{Extended_Pictographic}(?:️|‍\p{Extended_Pictographic})*)/gu;

  /** The first emoji in a string, or '' - used to face the ends of the scale. */
  const firstEmoji = (text) => {
    const found = String(text || '').match(
      /\p{Extended_Pictographic}(?:\uFE0F|\u200D\p{Extended_Pictographic})*/u
    );

    return found ? found[0] : '';
  };

  /** The same string with its emoji taken out, for where one already shows. */
  const withoutEmoji = (text) =>
    String(text || '')
      .replace(
        /\p{Extended_Pictographic}(?:\uFE0F|\u200D\p{Extended_Pictographic})*/gu,
        ''
      )
      .replace(/\s{2,}/g, ' ')
      .trim();

  const withEmoji = (text) => {
    const parts = String(text || '').split(EMOJI).filter((part) => part !== '');

    return parts.map((part) =>
      EMOJI.test(part) && (EMOJI.lastIndex = 0) === 0
        ? el('span', { class: 'sk-emoji', text: part })
        : document.createTextNode(part)
    );
  };

  /* ------------------------------------------------------------ rendering */

  const progress = () => {
    const total = state.questions.length;
    const current = Math.min(state.index + 1, total);
    const percent = total ? Math.round((current / total) * 100) : 0;

    const fill = el('div', { class: 'sk-progress__fill' });
    fill.style.width = percent + '%';

    return el(
      'div',
      {
        class: 'sk-progress',
        role: 'progressbar',
        'aria-valuemin': '1',
        'aria-valuemax': String(total),
        'aria-valuenow': String(current),
        'aria-valuetext': t('progress_label', 'Ερώτηση') + ' ' + current + ' / ' + total,
      },
      el('div', { class: 'sk-progress__track' }, fill),
      el('span', {
        class: 'sk-progress__text',
        text: current + ' / ' + total,
      })
    );
  };

  const scale = (question) => {
    const chosen = state.answers[question.key];
    const isIgnored = Boolean(state.ignored[question.key]);

    const options = [1, 2, 3, 4, 5].map((value) => {
      // The ends of the scale carry the meaning. Without them on the radio
      // itself a screen reader announces "1, 2, 3, 4, 5" and the user has no
      // idea which direction is which.
      let name = String(value);
      if (value === 1 && question.labelLow) {
        name = value + ' — ' + question.labelLow;
      } else if (value === 5 && question.labelHigh) {
        name = value + ' — ' + question.labelHigh;
      }

      const input = el('input', {
        type: 'radio',
        name: 'sk-' + question.key,
        value: String(value),
        checked: chosen === value,
        disabled: isIgnored,
        'aria-label': name,
      });

      input.addEventListener('change', () => {
        state.answers[question.key] = value;
        render();
      });

      // The ends of the scale carry a picture; the middle carries a number.
      // A digit says how far along you are, which is the least interesting
      // thing about 1 and 5 - what those two mean is the whole question.
      const face = value === 1 ? firstEmoji(question.labelLow)
        : value === 5 ? firstEmoji(question.labelHigh) : '';

      return el(
        'label',
        {
          class: 'sk-scale__option' + (face ? ' sk-scale__option--face' : ''),
          'data-checked': String(chosen === value),
        },
        input,
        face
          ? el('span', { class: 'sk-scale__face', 'aria-hidden': 'true', text: face })
          : el('span', { class: 'sk-scale__number', text: String(value) })
      );
    });

    return el(
      'div',
      {},
      el('div', { class: 'sk-scale', 'data-question': question.key }, options),
      el(
        'div',
        // Visible for sighted users; the same text is already on radios 1 and
        // 5, so announcing it twice would only be noise.
        // The emoji is on the radio above; repeating it here says the same
        // thing twice and halves the impact of the one that matters.
        { class: 'sk-scale__ends', 'aria-hidden': 'true' },
        el('span', { text: withoutEmoji(question.labelLow || '') }),
        el('span', { text: withoutEmoji(question.labelHigh || '') })
      )
    );
  };

  const toggles = (question) => {
    const dealbreaker = el('button', {
      type: 'button',
      class: 'sk-toggle',
      'aria-pressed': String(Boolean(state.dealbreakers[question.key])),
      text: t('dealbreaker_label', 'Το θέλω οπωσδήποτε'),
      onClick: () => {
        state.dealbreakers[question.key] = !state.dealbreakers[question.key];
        render();
      },
    });

    const dealbreakerInfo = infoAffordance(
      t('dealbreaker_help_label', 'Τι σημαίνει «το θέλω οπωσδήποτε»;'),
      t(
        'dealbreaker_help',
        'Πάτησέ το μόνο αν το θέλεις οπωσδήποτε. Οι ράτσες που δεν το ' +
          'καλύπτουν βγαίνουν εντελώς από τα αποτελέσματα — δεν χάνουν απλώς πόντους. ' +
          'Χρησιμοποίησέ το με φειδώ, αλλιώς μπορεί να μη μείνει καμία! 🐾'
      )
    );

    // The scale stops at 5, so "no preference" is the answer that does not fit
    // on it. Calling it 6-7 says that in two characters; the words stay
    // underneath, and the aria-label carries them for anyone not reading the
    // joke visually.
    const ignore = el('button', {
      type: 'button',
      class: 'sk-toggle sk-toggle--ignore',
      'aria-pressed': String(Boolean(state.ignored[question.key])),
      'aria-label': t('ignore_label', 'Δεν μου κάνει διαφορά'),
      onClick: () => {
        state.ignored[question.key] = !state.ignored[question.key];
        if (state.ignored[question.key]) {
          delete state.answers[question.key];
        }
        render();
      },
    });

    ignore.append(
      el('span', { class: 'sk-toggle__glyph', 'aria-hidden': 'true', text: '6–7' }),
      el('span', { class: 'sk-toggle__words', 'aria-hidden': 'true',
        text: t('ignore_label', 'Δεν μου κάνει διαφορά') })
    );

    const ignoreInfo = infoAffordance(
      t('ignore_help_label', 'Τι σημαίνει «δεν μου κάνει διαφορά»;'),
      t(
        'ignore_help',
        'Αν δεν έχεις γνώμη, μην μαντεύεις — πάτα το. Η ερώτηση αγνοείται τελείως ' +
          'αντί να μετρήσει σαν μέτριο «3», που θα αλλοίωνε το αποτέλεσμα.'
      )
    );

    // A question whose scale already ends in "does not matter" does not need
    // a second control saying the same thing; the sheet decides which those
    // are, per question.
    const row = el('div', { class: 'sk-toggles' });

    if (question.showDealbreaker !== false) {
      row.append(dealbreaker);
    }
    if (dealbreakerInfo && question.showDealbreaker !== false) {
      row.append(dealbreakerInfo.button);
    }

    if (question.showIgnore !== false) {
      row.append(ignore);
      if (ignoreInfo) {
        row.append(ignoreInfo.button);
      }
    }

    const wrapper = el('div', {}, row);
    if (dealbreakerInfo && question.showDealbreaker !== false) {
      wrapper.append(dealbreakerInfo.panel);
    }
    if (ignoreInfo && question.showIgnore !== false) {
      wrapper.append(ignoreInfo.panel);
    }

    return wrapper;
  };

  /**
   * Makes the terms box announce itself when someone tries to walk past it.
   *
   * The box was already there and already blocking; it was just quiet about
   * it, so the reader saw a dead button and no reason. Movement draws the eye
   * where a sentence does not - and the focus call means a screen reader is
   * told the same thing rather than left behind.
   */
  const nudgeTerms = () => {
    const gate = root.querySelector('.sk-terms');
    const box = root.querySelector('#sk-terms');

    if (!gate) {
      return;
    }

    gate.classList.remove('sk-terms--nudge');
    // Reading offsetWidth restarts the animation; without it a second press
    // does nothing at all.
    void gate.offsetWidth;
    gate.classList.add('sk-terms--nudge');

    gate.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (box) {
      box.focus({ preventScroll: true });
    }
  };

  const termsGate = () => {
    const checkbox = el('input', {
      type: 'checkbox',
      id: 'sk-terms',
      checked: state.termsAccepted,
    });

    checkbox.addEventListener('change', () => {
      state.termsAccepted = checkbox.checked;
      render();
    });

    // Not a footnote. Nothing past question one happens until this is ticked,
    // so it is styled as the gate it is rather than as small print.
    return el(
      'div',
      { class: 'sk-terms' + (state.termsAccepted ? ' sk-terms--done' : '') },
      el('span', {
        class: 'sk-terms__badge',
        text: t('terms_badge', 'ΠΡΟΫΠΟΘΕΣΗ'),
      }),
      el(
        'div',
        { class: 'sk-terms__body' },
        checkbox,
        el('label', { for: 'sk-terms', text: state.terms.label })
      )
    );
  };

  const questionView = () => {
    const question = state.questions[state.index];
    if (!question) {
      return null;
    }

    const helperInfo = infoAffordance(
      t('question_help_label', 'Περισσότερα για την ερώτηση'),
      question.helper || ''
    );

    const legend = el('legend', { class: 'sk-question__legend', text: question.label });
    if (helperInfo) {
      legend.append(helperInfo.button);
    }

    const fieldset = el(
      'fieldset',
      { class: 'sk-question__fieldset' },
      legend,
      helperInfo ? helperInfo.panel : null,
      scale(question),
      toggles(question)
    );

    return el('div', { class: 'sk-question' }, fieldset);
  };

  const controls = () => {
    const answered = state.answers[state.questions[state.index]?.key] !== undefined;
    const ignored = Boolean(state.ignored[state.questions[state.index]?.key]);
    const isLast = state.index >= state.questions.length - 1;

    // On question one there is nothing behind you - unless you came back from
    // a result, in which case Back means that result. A button that is present
    // and does nothing is worse than one that is not there.
    const canReturnToResult = state.index === 0 && state.lastResult !== null;

    const back = el('button', {
      type: 'button',
      class: 'sk-button sk-button--secondary',
      text: canReturnToResult
        ? t('back_to_result', 'Πίσω στα αποτελέσματα')
        : t('back_button', 'Πίσω'),
      onClick: () => {
        if (canReturnToResult) {
          root.replaceChildren(resultsView(state.lastResult));
          root.scrollIntoView({ behavior: 'smooth', block: 'start' });

          return;
        }

        state.index = Math.max(0, state.index - 1);
        render();
      },
    });

    // A disabled button cannot be clicked, so it cannot explain itself. This
    // one stays enabled while the terms are unaccepted purely so that
    // pressing it can point at what is missing.
    const blocked = !state.termsAccepted;

    const next = el('button', {
      type: 'button',
      class: 'sk-button' + (blocked ? ' sk-button--blocked' : ''),
      text: isLast ? t('submit_button', 'Δες τα αποτελέσματα') : t('next_button', 'Επόμενο'),
      disabled: state.busy || (!blocked && !(answered || ignored)),
      onClick: () => {
        if (blocked) {
          nudgeTerms();
          return;
        }

        if (isLast) {
          submit();
        } else {
          state.index += 1;
          render();
        }
      },
    });

    const controls = el('div', { class: 'sk-controls' });

    if (state.index > 0 || canReturnToResult) {
      controls.append(back);
    }

    controls.append(next);

    // A disabled button with no explanation reads as a broken page. Say what
    // is missing, next to the thing that is not working.
    if (!state.termsAccepted) {
      return el(
        'div',
        {},
        controls,
        el('p', {
          class: 'sk-controls__blocked',
          role: 'status',
          text: t('terms_required', 'Αποδέξου τους όρους πιο πάνω για να συνεχίσεις.'),
        })
      );
    }

    return controls;
  };

  const MEDALS = {
    gold:   { glyph: '🥇', rank: '1', label: 'Χρυσό' },
    silver: { glyph: '🥈', rank: '2', label: 'Ασημένιο' },
    bronze: { glyph: '🥉', rank: '3', label: 'Χάλκινο' },
  };

  /** The explanation lines for one breed, built once and shown in the drawer. */
  const explanationLines = (data) => {
    const lines = el('div', { class: 'sk-card__lines' });
    let count = 0;

    (data.aligned || []).forEach((line) => {
      count += 1;
      lines.append(
        el('p', { class: 'sk-line' },
          el('span', { class: 'sk-line__icon', 'aria-hidden': 'true', text: '✅' }),
          el('span', {}, el('strong', { text: line.label + ': ' }), line.body))
      );
    });

    (data.pitfalls || []).forEach((line) => {
      count += 1;
      lines.append(
        el('p', { class: 'sk-line sk-line--pitfall' },
          el('span', { class: 'sk-line__icon', 'aria-hidden': 'true', text: '⚠️' }),
          el('span', {}, el('strong', { text: line.label + ': ' }), line.body))
      );
    });

    (data.conflicts || []).forEach((label) => {
      count += 1;
      lines.append(
        el('p', { class: 'sk-line sk-line--pitfall' },
          el('span', { class: 'sk-line__icon', 'aria-hidden': 'true', text: '⛔' }),
          el('span', { text: t('conflict_label', 'Δεν καλύπτει: ') + label }))
      );
    });

    return count > 0 ? lines : null;
  };

  /**
   * One breed.
   *
   * The explanation is NOT inside the card. Three cards standing on steps have
   * a geometry, and expanding one of them moved the other two down the page -
   * the reader loses their place for the sake of text they did not ask to see
   * yet. The button opens a single drawer under the podium instead, so the
   * podium never moves.
   */
  const card = (data, medal, variant, drawer, rank) => {
    const shape = variant || (medal ? 'podium' : 'row');

    const picture = data.imageUrl
      ? el('img', {
          class: 'sk-card__image',
          src: data.imageUrl,
          alt: '',
          loading: 'lazy',
        })
      : el('div', { class: 'sk-card__placeholder', 'aria-hidden': 'true', text: '🐾' });

    // The photo goes to the article, exactly as the name does. A picture that
    // looks clickable and is not is a small betrayal repeated on every card.
    const media = data.permalink
      ? el('a', {
          class: 'sk-card__media',
          href: data.permalink,
          'aria-label': data.name,
          tabindex: '-1',
        }, picture)
      : picture;

    if (data.permalink) {
      media.addEventListener('click', () => track('card_clicked', data.breedKey));
    }

    const name = data.permalink
      ? el('a', { href: data.permalink, text: data.name })
      : document.createTextNode(data.name);

    const body = el('div', { class: 'sk-card__body' }, el('h3', { class: 'sk-card__name' }, name));

    const lines = explanationLines(data);

    if (lines && drawer) {
      const button = el('button', {
        type: 'button',
        class: 'sk-explain__button',
        'aria-expanded': 'false',
        'aria-controls': drawer.id,
        text: t('explain_label', 'Εξήγηση'),
      });

      button.addEventListener('click', () => {
        const opened = drawer.show({ ...data, medal: medal }, lines, button);
        if (opened) {
          track('explanation_opened', data.breedKey);
        }
      });

      body.append(button);
    }

    const info = MEDALS[medal];

    const scoreBadge = el(
      'div',
      { class: 'sk-medal' + (medal ? ' sk-medal--' + medal : '') },
      info
        ? el('span', { class: 'sk-medal__glyph', 'aria-hidden': 'true', text: info.glyph })
        : null,
      el('span', { class: 'sk-medal__score', text: String(data.score) + '%' })
    );

    if (info) {
      scoreBadge.setAttribute('aria-label', info.label + ': ' + data.score + '%');
    }

    const classes = ['sk-card', 'sk-card--' + shape];
    if (medal) {
      classes.push('sk-card--' + medal);
    }

    const article = el('article', { class: classes.join(' ') }, media, scoreBadge, body);

    // The runners-up have no medal, so the number is the only thing telling a
    // reader where they sit. Without it, seven cards look like an unordered
    // pile rather than places four through ten.
    if (rank) {
      article.prepend(el('span', { class: 'sk-rank', text: '#' + rank }));
    }

    if (shape === 'podium' && info) {
      article.append(
        el('div', { class: 'sk-step', 'aria-hidden': 'true' },
          el('span', { class: 'sk-step__rank', text: info.rank })
        )
      );
    }

    return article;
  };

  /**
   * The single panel every Εξήγηση button opens.
   *
   * One at a time, under the podium, so the three cards keep their geometry no
   * matter what is open.
   */
  let drawerCount = 0;

  const makeDrawer = () => {
    drawerCount += 1;
    const id = 'sk-drawer-' + drawerCount;

    const title = el('h4', { class: 'sk-drawer__title' });
    const content = el('div', {});
    const node = el('div', { class: 'sk-drawer', id: id, hidden: true }, title, content);

    let currentButton = null;

    const close = () => {
      node.hidden = true;
      if (currentButton) {
        currentButton.setAttribute('aria-expanded', 'false');
        currentButton = null;
      }
    };

    return {
      id: id,
      node: node,
      show: (data, lines, button) => {
        if (currentButton === button) {
          close();
          return false;
        }

        close();
        title.textContent = data.name;
        content.replaceChildren(lines);
        node.hidden = false;

        // Sit under the card that was pressed: gold is centre, silver left,
        // bronze right. A panel that always opens hard left looks like it
        // belongs to the first card whichever one you asked.
        node.dataset.align = data.medal || 'full';
        button.setAttribute('aria-expanded', 'true');
        currentButton = button;

        return true;
      },
    };
  };

  /**
   * The runners-up, as an endless carousel.
   *
   * Three decisions worth stating:
   *
   * - The track is duplicated once and the scroll position wraps at the
   *   halfway mark, which is what makes it endless without ever jumping
   *   somewhere the reader can see. The copies are aria-hidden: a screen
   *   reader must not read the same seven breeds twice.
   * - The buttons are the real control. Auto-scroll is decoration and stops
   *   the moment anyone touches anything; the arrows always work, including
   *   under prefers-reduced-motion where nothing moves on its own.
   * - The scrollbar is hidden because the arrows say the same thing better,
   *   and a scrollbar under a row of cards reads as an accident.
   */
  const carousel = (cards) => {
    const track = el('div', { class: 'sk-runners' });
    cards.forEach((node) => track.append(node));

    // The second set exists only to be scrolled into; nothing may reach it by
    // keyboard or hear it announced.
    cards.forEach((node) => {
      const clone = node.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.querySelectorAll('a, button').forEach((focusable) => {
        focusable.setAttribute('tabindex', '-1');
      });
      track.append(clone);
    });

    const half = () => track.scrollWidth / 2;

    const wrap = () => {
      const point = half();
      if (point < 1) {
        return;
      }
      if (track.scrollLeft >= point) {
        track.scrollLeft -= point;
      } else if (track.scrollLeft < 0) {
        track.scrollLeft += point;
      }
    };

    const stepSize = () => {
      const first = track.firstElementChild;
      return first ? first.getBoundingClientRect().width + 10 : 170;
    };

    let auto = true;
    const stopAuto = () => { auto = false; };

    const nudge = (direction) => {
      stopAuto();
      wrap();
      track.scrollBy({ left: direction * stepSize(), behavior: 'smooth' });
    };

    const previous = el('button', {
      type: 'button',
      class: 'sk-carousel__arrow sk-carousel__arrow--prev',
      'aria-label': t('carousel_prev', 'Προηγούμενες'),
      text: '‹',
      onClick: () => nudge(-1),
    });

    const next = el('button', {
      type: 'button',
      class: 'sk-carousel__arrow sk-carousel__arrow--next',
      'aria-label': t('carousel_next', 'Επόμενες'),
      text: '›',
      onClick: () => nudge(1),
    });

    ['pointerdown', 'wheel', 'touchstart', 'keydown'].forEach((event) => {
      track.addEventListener(event, stopAuto, { passive: true });
    });
    track.addEventListener('focusin', stopAuto);

    let paused = false;
    track.addEventListener('mouseenter', () => { paused = true; });
    track.addEventListener('mouseleave', () => { paused = false; });

    const reduced = window.matchMedia
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!reduced) {
      let last = null;
      let started = false;

      // scrollLeft is rounded to whole pixels on assignment, and 22px/s is a
      // third of a pixel per frame - assigning that repeatedly rounds to zero
      // every time and the track never moves. The true position is kept here
      // as a float and only handed over once it has grown past a pixel.
      let position = 0;
      const speed = 22; // pixels per second: readable, not a slideshow

      const frame = (now) => {
        // This runs before the track is in the document: carousel() builds it
        // and the caller appends it afterwards. Returning on the first frame
        // would kill the loop permanently, so only a track that was once
        // connected and has since been removed ends it.
        if (!track.isConnected) {
          if (started) {
            return;
          }
          window.requestAnimationFrame(frame);
          return;
        }

        if (!started) {
          started = true;
          position = track.scrollLeft;
        }

        if (auto && !paused && last !== null && track.scrollWidth > track.clientWidth) {
          // A reader dragging the track moves scrollLeft under us; follow it
          // rather than yanking them back to where we thought we were.
          if (Math.abs(track.scrollLeft - position) > 2) {
            position = track.scrollLeft;
          }

          position += (speed * (now - last)) / 1000;

          const point = track.scrollWidth / 2;
          if (point >= 1 && position >= point) {
            position -= point;
          }

          track.scrollLeft = position;
        }

        last = now;
        window.requestAnimationFrame(frame);
      };

      window.requestAnimationFrame(frame);
    }

    return el('div', { class: 'sk-carousel' }, previous, track, next);
  };
  const resultsView = (result) => {
    const wrapper = el('div', { 'aria-live': 'polite' });

    if (!result.hasEnoughEvidence) {
      wrapper.append(
        el('div', { class: 'sk-note' },
          el('p', {
            text: t(
              'not_enough_evidence',
              'Χρειάζομαι λίγες ακόμη απαντήσεις για να σου πω κάτι χρήσιμο.'
            ),
          })
        )
      );
      return wrapper;
    }

    if (result.podium.length === 0) {
      wrapper.append(
        el('div', { class: 'sk-note' },
          el('p', {
            text:
              t('all_excluded', 'Καμία ράτσα δεν περνάει όλα τα απαραίτητα κριτήριά σου.') +
              (result.blockingLabel
                ? ' ' + t('relax_hint', 'Δοκίμασε να χαλαρώσεις:') + ' ' + result.blockingLabel
                : ''),
          })
        )
      );
      return wrapper;
    }

    wrapper.append(el('h2', { text: t('results_title', 'Η επόμενη σκυλίτσα σου;') }));

    if (result.isCloseCall) {
      wrapper.append(
        el('div', { class: 'sk-note' },
          el('p', {
            text: t(
              'close_call',
              'Οι τρεις πρώτες σκυλίτσες ταιριάζουν. Δες την «Εξήγηση» και πάτα τη φωτογραφία για λεπτομέρειες.'
            ),
          })
        )
      );
    }

    // DOM order stays 1, 2, 3 so a screen reader reads the winner first. CSS
    // moves gold to the middle and raises its step. The visual order and the
    // reading order are allowed to disagree, and here they should.
    const medals = ['gold', 'silver', 'bronze'];
    const podiumDrawer = makeDrawer();
    const podium = el('div', { class: 'sk-podium' });
    result.podium.forEach((data, index) => {
      podium.append(card(data, medals[index], 'podium', podiumDrawer));
    });
    wrapper.append(podium, podiumDrawer.node);

    if (result.runnersUp.length) {
      wrapper.append(el('h3', { text: t('more_results', 'Και αυτές αξίζουν ματιά') }));
      const runnersDrawer = makeDrawer();
      const podiumSize = result.podium.length;
      const cards = result.runnersUp.map((data, index) =>
        card(data, null, 'row', runnersDrawer, podiumSize + index + 1));
      wrapper.append(carousel(cards), runnersDrawer.node);
    }

    if (result.excluded.length) {
      const summary = el('summary', {
        text:
          result.excluded.length +
          ' ' +
          t('excluded_label', 'ράτσες βγήκαν εκτός επειδή δεν καλύπτουν κάτι που θες οπωσδήποτε'),
      });
      const details = el('details', {}, summary);
      details.addEventListener('toggle', () => {
        if (details.open) {
          track('excluded_opened', '');
        }
      });
      const list = el('div', { class: 'sk-runners' });
      result.excluded.forEach((data) => list.append(card(data, null, 'row', null, 0)));
      details.append(list);
      wrapper.append(details);
    }

    // The disclaimer sits with the podium, not beneath the page, because it is
    // read at the moment of reliance (spec 11.4).
    wrapper.append(
      el(
        'div',
        { class: 'sk-disclaimer' },
        el('h3', { text: result.disclaimerTitle || t('disclaimer_title', 'Σημαντικό πριν αποφασίσεις') }),
        el('p', {
          text:
            result.disclaimerBody ||
            t(
              'disclaimer_body',
              'Κάθε σκυλί είναι μοναδικό. Οι τάσεις της ράτσας είναι ένας μόνο παράγοντας — ' +
                'μίλησε με κτηνίατρο, εκπαιδευτή ή το καταφύγιο πριν αποφασίσεις.'
            ),
        })
      )
    );

    // A result is a starting point, not a verdict. Someone who answered a
    // question wrongly, or whose circumstances are different from the ones
    // they described, has no way back without this.
    const again = el('button', {
      type: 'button',
      class: 'sk-button sk-button--secondary sk-again',
      text: t('restart_label', 'Ξανά από την αρχή'),
      onClick: () => {
        track('restart', '');

        // A result URL in the address bar puts the old answers straight back
        // on screen at the next refresh, so it has to go. location.pathname
        // is no good here: on a result page that IS /match/<token>/, so
        // replacing the URL with it changed nothing at all.
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', quizPath());
        }

        state.lastResult = result;
        state.token = null;
        state.answers = {};
        state.dealbreakers = {};
        state.ignored = {};
        state.index = 0;

        // A new run is a new session and a new stored result, so the terms
        // are accepted again rather than carried over from the last one
        // (spec 11.4). Leaving it accepted also hid the gate while the Next
        // button still waited on it, which read as a broken page.
        state.termsAccepted = false;

        render();
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
      },
    });

    wrapper.append(el('div', { class: 'sk-again__row' }, again));

    return wrapper;
  };

  /* --------------------------------------------------------------- actions */

  const submit = async () => {
    if (state.busy) {
      return;
    }
    state.busy = true;
    render();

    try {
      const payload = await post('/match/score', {
        token: state.token,
        answers: state.answers,
        dealbreakers: Object.keys(state.dealbreakers).filter((k) => state.dealbreakers[k]),
        ignored: Object.keys(state.ignored).filter((k) => state.ignored[k]),
        termsAccepted: state.termsAccepted,
      });

      state.token = payload.token;

      readingControl();
      root.replaceChildren(resultsView(payload.result));

      if (payload.resultUrl && window.history && window.history.replaceState) {
        window.history.replaceState({}, '', payload.resultUrl);
      }

      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      root.replaceChildren(
        el('div', { class: 'sk-note' },
          el('p', { text: t('error_generic', 'Κάτι πήγε στραβά. Δοκίμασε ξανά σε λίγο.') })
        )
      );
    } finally {
      state.busy = false;
    }
  };

  const render = () => {
    const view = el('div', {});


    view.append(progress());

    const question = questionView();
    if (question) {
      view.append(question);
    }

    // The terms sit directly above the button they block, which is where a
    // reader expects to find them and where they are actually relevant. At
    // the top of the page they were a banner to scroll past.
    if (!state.termsAccepted) {
      view.append(termsGate());
    }

    view.append(controls());

    root.replaceChildren(view);
  };

  /* ------------------------------------------------------------------ boot */

  const showStoredResult = async (token) => {
    try {
      const response = await fetch(api + '/match/' + encodeURIComponent(token));
      if (!response.ok) {
        throw new Error('result failed');
      }
      const payload = await response.json();

      state.token = token;
      state.uiTexts = payload.uiTexts || {};

      readingControl();
      root.replaceChildren(resultsView(payload.result));
    } catch (error) {
      root.replaceChildren(
        el('div', { class: 'sk-note' },
          el('p', { text: 'Δεν βρέθηκαν αποτελέσματα για αυτόν τον σύνδεσμο.' })
        )
      );
    }
  };

  /**
   * Where the quiz lives, as opposed to where a result lives.
   *
   * Recorded when the page first loads: if that was a result URL the visitor
   * followed a shared link and we have no idea which page embeds the quiz, so
   * the site root is the honest answer.
   */
  const RESULT_PATH = /^\/match\/[a-z0-9]{12}\/?$/;

  const embedPath = RESULT_PATH.test(window.location.pathname)
    ? null
    : window.location.pathname;

  const quizPath = () => embedPath || '/';

  const boot = async () => {
    // Applied once, not on every render: it only reads storage and sets two
    // custom properties on the root.
    readingControl();
    if (storedToken) {
      await showStoredResult(storedToken);
      return;
    }

    try {
      const response = await fetch(api + '/match/questions');
      if (!response.ok) {
        throw new Error('questions failed');
      }
      const payload = await response.json();

      state.questions = payload.questions || [];
      state.uiTexts = payload.uiTexts || {};
      state.terms = payload.terms || { version: '', label: '' };

      if (state.questions.length === 0) {
        root.replaceChildren(
          el('div', { class: 'sk-note' },
            el('p', { text: t('not_configured', 'Το τεστ δεν έχει ρυθμιστεί ακόμη.') })
          )
        );
        return;
      }

      render();
    } catch (error) {
      root.replaceChildren(
        el('div', { class: 'sk-note' },
          el('p', { text: 'Κάτι πήγε στραβά κατά τη φόρτωση.' })
        )
      );
    }
  };

  boot();
})();
