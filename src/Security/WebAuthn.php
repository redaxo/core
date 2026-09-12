<?php

namespace Redaxo\Core\Security;

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn as BaseWebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Session;
use Redaxo\Core\Util\Type;
use stdClass;

/**
 * @internal
 */
final class WebAuthn
{
    private const string SESSION_CHALLENGE_CREATE = 'webauthn_challenge_create';
    private const string SESSION_CHALLENGE_GET = 'webauthn_challenge_get';

    public function getCreateArgs(): string
    {
        $user = Core::requireUser();

        $webauthn = $this->createWebauthnBase();
        $args = $webauthn->getCreateArgs((string) $user->id, $user->login, $user->name ?? $user->login, requireResidentKey: true, requireUserVerification: true);

        Session::start()->set(self::SESSION_CHALLENGE_CREATE, $webauthn->getChallenge());

        return json_encode($args);
    }

    /** @return array{string, string} */
    public function processCreate(string $data): array
    {
        $data = Type::instanceOf(json_decode($data), stdClass::class);

        $clientDataJSON = base64_decode(Type::string($data->clientDataJSON));
        $attestationObject = base64_decode(Type::string($data->attestationObject));

        /** @var ByteBuffer $challenge */
        $challenge = Session::start()->get(self::SESSION_CHALLENGE_CREATE);

        $data = $this->createWebauthnBase()->processCreate($clientDataJSON, $attestationObject, $challenge, requireUserVerification: true);

        $credentialId = Type::string($data->credentialId);
        $credentialId = rtrim(strtr(base64_encode($credentialId), '+/', '-_'), '=');

        return [$credentialId, Type::string($data->credentialPublicKey)];
    }

    public function getGetArgs(?string $id = null): string
    {
        $webauthn = $this->createWebauthnBase();
        $args = $webauthn->getGetArgs($id ? [ByteBuffer::fromBase64Url($id)] : [], requireUserVerification: true);

        Session::start()->set(self::SESSION_CHALLENGE_GET, $webauthn->getChallenge());

        return json_encode($args);
    }

    /** @return array{string, User}|null */
    public function processGet(string $data): ?array
    {
        $data = Type::instanceOf(json_decode($data), stdClass::class);

        $id = (int) base64_decode(Type::string($data->userHandle));

        $user = User::get($id);

        if (!$user) {
            return null;
        }

        $sql = Sql::factory();
        $sql->setQuery('SELECT public_key FROM ' . Core::getTable('user_passkey') . ' WHERE id = ? AND user_id = ?', [$data->id, $id]);

        if (!$sql->getRows()) {
            return null;
        }

        $clientDataJSON = base64_decode(Type::string($data->clientDataJSON));
        $authenticatorData = base64_decode(Type::string($data->authenticatorData));
        $signature = base64_decode(Type::string($data->signature));

        /** @var ByteBuffer $challenge */
        $challenge = Session::start()->get(self::SESSION_CHALLENGE_GET);

        try {
            $this->createWebauthnBase()->processGet($clientDataJSON, $authenticatorData, $signature, Type::string($sql->getValue('public_key')), $challenge, requireUserVerification: true);
        } catch (WebAuthnException) {
            return null;
        }

        return [Type::string($data->id), $user];
    }

    private function createWebauthnBase(): BaseWebAuthn
    {
        return new BaseWebAuthn(Core::getServerName(), Core::getRequest()->getHost());
    }
}
