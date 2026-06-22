<?php

namespace Redaxo\Core\Cronjob\Type;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/** @internal */
final class UrlRequestType extends AbstractType
{
    #[Override]
    public function execute(): bool
    {
        // Redirects werden vom HTTP-Client automatisch verfolgt, sofern aktiviert (Default an)
        $options = ['max_redirects' => $this->getParam('redirect', true) ? 10 : 0];

        if ('|1|' == $this->getParam('http-auth')) {
            $options['auth_basic'] = [$this->getParam('user'), $this->getParam('password')];
        }

        if ('' != ($post = $this->getParam('post'))) {
            $options['body'] = (string) $post;
        }

        $method = isset($options['body']) ? 'POST' : 'GET';

        try {
            $response = Core::getHttpClient()->request($method, $this->getParam('url'), $options);

            $statusCode = $response->getStatusCode();
            $this->message = trim($statusCode . ' ' . (HttpResponse::$statusTexts[$statusCode] ?? ''));

            return $statusCode >= 200 && $statusCode < 300;
        } catch (TransportExceptionInterface $e) {
            $this->message = $e->getMessage();
            return false;
        }
    }

    #[Override]
    public function getTypeName(): string
    {
        return I18n::msg('cronjob_type_urlrequest');
    }

    #[Override]
    public function getParamFields(): array
    {
        return [
            [
                'label' => I18n::msg('cronjob_type_urlrequest_url'),
                'name' => 'url',
                'type' => 'text',
                'default' => 'https://',
            ],
            [
                'label' => I18n::msg('cronjob_type_urlrequest_post'),
                'name' => 'post',
                'type' => 'text',
            ],
            [
                'name' => 'http-auth',
                'type' => 'checkbox',
                'options' => [1 => I18n::msg('cronjob_type_urlrequest_httpauth')],
            ],
            [
                'label' => I18n::msg('cronjob_type_urlrequest_user'),
                'name' => 'user',
                'type' => 'text',
                'visible_if' => ['http-auth' => 1],
            ],
            [
                'label' => I18n::msg('cronjob_type_urlrequest_password'),
                'name' => 'password',
                'type' => 'text',
                'visible_if' => ['http-auth' => 1],
            ],
        ];
    }
}
