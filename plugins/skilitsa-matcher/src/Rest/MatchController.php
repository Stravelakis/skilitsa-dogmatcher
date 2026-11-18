<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Rest;

use InvalidArgumentException;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Matcher\Content\ContentProvider;
use Skilitsa\Matcher\Session\MatchSession;
use Skilitsa\Matcher\Session\SessionRepository;
use Skilitsa\Matcher\Session\Token;
use Skilitsa\Matcher\Telemetry\EventRecorder;
use Skilitsa\Matcher\View\ResultViewModel;
use Skilitsa\Matcher\View\ResultViewModelBuilder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The public scoring API.
 *
 * Endpoints are open because the quiz must work for logged-out visitors, but
 * every one is rate-limited and none accepts an identifier. Scoring happens
 * here, never in the browser: v1 inlined the whole 397 KB breed matrix into
 * every quiz page.
 */
final class MatchController
{
    public const NAMESPACE = 'skilitsa/v1';
    public const TERMS_VERSION = '2026-08-21';

    public function __construct(
        private readonly ContentProvider $content,
        private readonly SessionRepository $sessions,
        private readonly ResultViewModelBuilder $builder,
        private readonly EventRecorder $events,
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/match/questions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'questions'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/match/score', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'score'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/match/event', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'event'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/match/(?P<token>[a-z0-9]{12})', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'result'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function questions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->limiter->allows('questions')) {
            return $this->tooMany();
        }

        return new WP_REST_Response([
            'questions' => $this->content->questionsForFlow(),
            'uiTexts'   => $this->content->uiTexts(),
            'flowMode'  => $this->content->flowMode(),
            'terms'     => [
                'version' => self::TERMS_VERSION,
                'label'   => $this->content->uiText(
                    'terms_checkbox',
                    'Καταλαβαίνω ότι αυτό είναι ενημερωτικό, όχι επαγγελματική συμβουλή.'
                ),
            ],
        ]);
    }

    public function score(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->limiter->allows('score')) {
            return $this->tooMany();
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('skilitsa_bad_body', 'Malformed request.', ['status' => 400]);
        }

        try {
            $answers = $this->answerSetFrom($body);
        } catch (InvalidArgumentException $e) {
            return new WP_Error('skilitsa_bad_answers', $e->getMessage(), ['status' => 400]);
        }

        $token = isset($body['token']) && Token::isValid((string) $body['token'])
            ? (string) $body['token']
            : Token::generate();

        $result = $this->content->matchService()->match($answers, $this->content->scoringConfig());

        $model = $this->builder->build(
            $result,
            $this->content->breedDisplay(),
            $this->content->templates(),
            $this->content->traitCopy()
        );

        $session = new MatchSession(
            token: $token,
            answers: $answers->answers,
            dealbreakers: $this->stringList($body['dealbreakers'] ?? []),
            ignored: $this->stringList($body['ignored'] ?? []),
            flowMode: $this->content->flowMode(),
            termsVersion: !empty($body['termsAccepted']) ? self::TERMS_VERSION : '',
        );

        $this->sessions->save($session);
        $this->sessions->saveResult($token, $result, wp_json_encode($this->serialise($model)) ?: '');
        $this->events->recordCompletion($token, $result);

        return new WP_REST_Response([
            'token'     => $token,
            'resultUrl' => home_url('/match/' . $token . '/'),
            'result'    => $this->serialise($model),
        ]);
    }

    public function result(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->limiter->allows('result')) {
            return $this->tooMany();
        }

        $token   = (string) $request->get_param('token');
        $session = $this->sessions->find($token);

        if ($session === null) {
            return new WP_Error('skilitsa_not_found', 'Result not found.', ['status' => 404]);
        }

        if ($session->resultSnapshot !== null && $session->resultSnapshot !== '') {
            $decoded = json_decode($session->resultSnapshot, true);
            if (is_array($decoded)) {
                return new WP_REST_Response([
                    'token'   => $token,
                    'result'  => $decoded,
                    'uiTexts' => $this->content->uiTexts(),
                ]);
            }
        }

        // No snapshot: recompute rather than 404. Scores may differ if breed
        // data changed since, which is why the snapshot exists.
        $result = $this->content->matchService()->match($session->toAnswerSet(), $this->content->scoringConfig());
        $model  = $this->builder->build(
            $result,
            $this->content->breedDisplay(),
            $this->content->templates(),
            $this->content->traitCopy()
        );

        return new WP_REST_Response([
            'token'   => $token,
            'result'  => $this->serialise($model),
            'uiTexts' => $this->content->uiTexts(),
        ]);
    }

    public function event(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->limiter->allows('event')) {
            return $this->tooMany();
        }

        $body = $request->get_json_params();
        if (!is_array($body) || !Token::isValid((string) ($body['token'] ?? ''))) {
            return new WP_Error('skilitsa_bad_event', 'Malformed event.', ['status' => 400]);
        }

        $this->events->record(
            (string) $body['token'],
            (string) ($body['type'] ?? ''),
            (string) ($body['target'] ?? '')
        );

        return new WP_REST_Response(['ok' => true]);
    }

    /** @param array<string,mixed> $body */
    private function answerSetFrom(array $body): AnswerSet
    {
        $answers = [];
        foreach ((array) ($body['answers'] ?? []) as $key => $value) {
            $answers[(string) $key] = $value;
        }

        return new AnswerSet(
            $answers,
            $this->stringList($body['dealbreakers'] ?? []),
            $this->stringList($body['ignored'] ?? [])
        );
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $value));
    }

    /** @return array<string,mixed> */
    private function serialise(ResultViewModel $model): array
    {
        $card = static fn ($c): array => [
            'breedKey'  => $c->breedKey,
            'name'      => $c->name,
            'score'     => $c->score,
            'imageUrl'  => $c->imageUrl,
            'permalink' => $c->permalink,
            'aligned'   => array_map(static fn ($l): array => ['label' => $l->label, 'body' => $l->body], $c->aligned),
            'pitfalls'  => array_map(static fn ($l): array => ['label' => $l->label, 'body' => $l->body], $c->pitfalls),
            'conflicts' => $c->conflicts,
        ];

        return [
            'podium'            => array_map($card, $model->podium),
            'runnersUp'         => array_map($card, $model->runnersUp),
            'excluded'          => array_map($card, $model->excluded),
            'isCloseCall'       => $model->isCloseCall,
            'hasEnoughEvidence' => $model->hasEnoughEvidence,
            'blockingLabel'     => $model->blockingDealbreakerLabel,
            'disclaimerTitle'   => $model->disclaimerTitle,
            'disclaimerBody'    => $model->disclaimerBody,
            'traitsKnown'       => $model->traitsKnown,
        ];
    }

    private function tooMany(): WP_Error
    {
        return new WP_Error(
            'skilitsa_rate_limited',
            'Πολλά αιτήματα. Δοκίμασε ξανά σε λίγο.',
            ['status' => 429]
        );
    }
}
