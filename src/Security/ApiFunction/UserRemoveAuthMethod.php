<?php

namespace Redaxo\Core\Security\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('user_remove_auth_method')]
class UserRemoveAuthMethod extends ApiFunction
{
    public function execute(): Result
    {
        $userId = Request::get('user_id', 'int');
        $user = Core::requireUser();

        if ($userId !== $user->getId() && !$user->isAdmin() && (!$user->hasPerm('users[]') || User::require($userId)->isAdmin())) {
            throw new ApiFunctionException('Permission denied');
        }

        if (Request::get('password', 'bool')) {
            return $this->removePassword($userId);
        }

        return $this->removePasskey($userId);
    }

    private function removePassword(int $userId): Result
    {
        $sql = Sql::factory()
            ->setTable(Core::getTable('user'))
            ->setWhere(['id' => $userId])
            ->setValue('password', null)
            ->addGlobalUpdateFields()
            ->setValue('password_change_required', 0)
            ->setDateTimeValue('password_changed', time())
            ->update();

        if (!$sql->getRows()) {
            return new Result(false, I18n::msg('password_remove_error'));
        }

        User::clearInstance($userId);
        Core::getProperty('login')->changedPassword(null);

        return new Result(true, I18n::msg('password_removed'));
    }

    private function removePasskey(int $userId): Result
    {
        $passkeyId = Request::get('passkey_id', 'string');

        $sql = Sql::factory()
            ->setTable(Core::getTable('user_passkey'))
            ->setWhere(['id' => $passkeyId, 'user_id' => $userId])
            ->delete();

        if (!$sql->getRows()) {
            return new Result(false, I18n::msg('passkey_remove_error'));
        }

        return new Result(true, I18n::msg('passkey_removed'));
    }
}
