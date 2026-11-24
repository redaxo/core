<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector as CodeQuality;
use Rector\CodingStyle\Rector as CodingStyle;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector as Php55;
use Rector\Php70\Rector as Php70;
use Rector\Php73\Rector as Php73;
use Rector\Php74\Rector as Php74;
use Rector\Php80\Rector as Php80;
use Rector\Php81\Rector as Php81;
use Rector\Php82\Rector as Php82;
use Rector\Privatization\Rector as Privatization;
use Rector\Removing\Rector\ClassMethod\ArgumentRemoverRector;
use Rector\Removing\ValueObject\ArgumentRemover;
use Rector\Renaming\Rector\ClassConstFetch\RenameClassConstFetchRector;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Renaming\Rector\PropertyFetch\RenamePropertyRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Rector\Renaming\ValueObject\RenameClassAndConstFetch;
use Rector\Renaming\ValueObject\RenameProperty;
use Rector\Transform\Rector\StaticCall\StaticCallToNewRector;
use Rector\Transform\ValueObject\StaticCallToNew;
use Rector\TypeDeclaration\Rector as TypeDeclaration;
use Rector\ValueObject\PhpVersion;
use Redaxo\Core\Addon;
use Redaxo\Core\ApiFunction;
use Redaxo\Core\Backend;
use Redaxo\Core\Base;
use Redaxo\Core\Cache;
use Redaxo\Core\Config;
use Redaxo\Core\Console;
use Redaxo\Core\Content;
use Redaxo\Core\Core;
use Redaxo\Core\Cronjob;
use Redaxo\Core\Database;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\Exception;
use Redaxo\Core\ExtensionPoint;
use Redaxo\Core\Filesystem;
use Redaxo\Core\Http;
use Redaxo\Core\Language;
use Redaxo\Core\Log;
use Redaxo\Core\Mailer;
use Redaxo\Core\MediaManager;
use Redaxo\Core\MediaPool;
use Redaxo\Core\Security;
use Redaxo\Core\Setup;
use Redaxo\Core\SystemReport;
use Redaxo\Core\Translation;
use Redaxo\Core\Util;
use Redaxo\Core\Validator;
use Redaxo\Core\View;
use Redaxo\Rector\Rule as RedaxoRule;
use Redaxo\Rector\ValueObject\FuncCallToStaticCall;
use Redaxo\Rector\ValueObject\MethodCallToPropertyAssign;
use Redaxo\Rector\ValueObject\MethodCallToPropertyFetch;
use Redaxo\Rector\ValueObject\MethodCallToStaticCall;
use Redaxo\Rector\ValueObject\NewToStaticCall;
use Redaxo\Rector\ValueObject\RenameStaticMethod;
use Redaxo\Rector\ValueObject\ReplaceArgumentValue;
use Redaxo\Rector\ValueObject\SetterCallToConstructorArgument;
use Redaxo\Rector\ValueObject\StaticCallToStaticPropertyAssign;
use Redaxo\Rector\ValueObject\StaticCallToStaticPropertyFetch;

return RectorConfig::configure()
    ->withPaths([
        '.tools/fixtures/',
        'addons',
        'boot/',
        'composer-plugin/',
        'fragments/',
        'pages/',
        'project/bin/console',
        'project/public/',
        'project/src/',
        'setup/',
        'src/',
        'tests/',
    ])
    ->withParallel()
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withPreparedSets(typeDeclarations: false, privatization: true)
    // ->withPhpSets()
    ->withImportNames(removeUnusedImports: false)
    ->withRules([
        CodeQuality\Assign\CombinedAssignRector::class,
        CodeQuality\BooleanNot\SimplifyDeMorganBinaryRector::class,
        CodeQuality\Class_\InlineConstructorDefaultToPropertyRector::class,
        CodeQuality\Class_\ConvertStaticToSelfRector::class,
        CodeQuality\Foreach_\SimplifyForeachToCoalescingRector::class,
        CodeQuality\FuncCall\SingleInArrayToCompareRector::class,
        CodeQuality\Identical\SimplifyBoolIdenticalTrueRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\If_\SimplifyIfReturnBoolRector::class,
        CodeQuality\NullsafeMethodCall\CleanupUnneededNullsafeOperatorRector::class,
        CodeQuality\Ternary\UnnecessaryTernaryExpressionRector::class,
        CodingStyle\ClassConst\RemoveFinalFromConstRector::class,
        // CodingStyle\String_\SimplifyQuoteEscapeRector::class,
        Php55\ClassConstFetch\StaticToSelfOnFinalClassRector::class,
        Php70\StmtsAwareInterface\IfIssetToCoalescingRector::class,
        Php70\Ternary\TernaryToNullCoalescingRector::class,
        Php80\Catch_\RemoveUnusedVariableInCatchRector::class,
        Php80\Identical\StrEndsWithRector::class,
        Php80\Identical\StrStartsWithRector::class,
        Php80\NotIdentical\StrContainsRector::class,
        Php80\Switch_\ChangeSwitchToMatchRector::class,
        Php81\Array_\ArrayToFirstClassCallableRector::class,
        Php81\Property\ReadOnlyPropertyRector::class,
        Php82\Class_\ReadOnlyClassRector::class,
        Privatization\Class_\FinalizeTestCaseClassRector::class,

        // Own rules
        RedaxoRule\UnderscoreToCamelCasePropertyNameRector::class,
        RedaxoRule\UnderscoreToCamelCaseVariableNameRector::class,
    ])
    ->withSkip([
        Php73\FuncCall\StringifyStrNeedlesRector::class,
        Php74\Closure\ClosureToArrowFunctionRector::class,
        Php81\FuncCall\NullToStrictStringFuncCallArgRector::class,
        TypeDeclaration\ArrowFunction\AddArrowFunctionReturnTypeRector::class,
        TypeDeclaration\Closure\AddClosureVoidReturnTypeWhereNoReturnRector::class,
    ])

    // Upgrade REDAXO 5 to 6
    ->withRules([
        RedaxoRule\LiteralTableNameRector::class,
    ])
    ->withConfiguredRule(RenameClassRector::class, [
        'rex' => Core::class,
        'rex_package_interface' => Addon\Addon::class,
        'rex_null_package' => Addon\Addon::class,
        'rex_package' => Addon\Addon::class,
        'rex_package_manager' => Addon\AddonManager::class,
        'rex_addon' => Addon\Addon::class,
        'rex_addon_interface' => Addon\Addon::class,
        'rex_addon_manager' => Addon\AddonManager::class,
        'rex_null_addon' => Addon\Addon::class,
        'rex_api_exception' => ApiFunction\Exception\ApiFunctionException::class,
        'rex_api_function' => ApiFunction\ApiFunction::class,
        'rex_api_result' => ApiFunction\Result::class,
        'rex_api_article2category' => Content\ApiFunction\ArticleToCategory::class,
        'rex_api_article2startarticle' => Content\ApiFunction\ArticleToStartArticle::class,
        'rex_api_article_add' => Content\ApiFunction\ArticleAdd::class,
        'rex_api_article_copy' => Content\ApiFunction\ArticleCopy::class,
        'rex_api_article_delete' => Content\ApiFunction\ArticleDelete::class,
        'rex_api_article_edit' => Content\ApiFunction\ArticleEdit::class,
        'rex_api_article_move' => Content\ApiFunction\ArticleMove::class,
        'rex_api_article_status' => Content\ApiFunction\ArticleStatusChange::class,
        'rex_api_category2article' => Content\ApiFunction\CategoryToArticle::class,
        'rex_api_category_add' => Content\ApiFunction\CategoryAdd::class,
        'rex_api_category_delete' => Content\ApiFunction\CategoryDelete::class,
        'rex_api_category_edit' => Content\ApiFunction\CategoryEdit::class,
        'rex_api_category_move' => Content\ApiFunction\CategoryMove::class,
        'rex_api_category_status' => Content\ApiFunction\CategoryStatusChange::class,
        'rex_api_content_copy' => Content\ApiFunction\ContentCopy::class,
        'rex_api_content_move_slice' => Content\ApiFunction\ArticleSliceMove::class,
        'rex_api_content_slice_status' => Content\ApiFunction\ArticleSliceStatusChange::class,
        'rex_api_has_user_session' => Security\ApiFunction\UserHasSession::class,
        'rex_api_user_impersonate' => Security\ApiFunction\UserImpersonate::class,
        'rex_api_user_remove_auth_method' => Security\ApiFunction\UserRemoveAuthMethod::class,
        'rex_api_user_remove_session' => Security\ApiFunction\UserRemoveSession::class,
        'rex_api_user_session_status' => Security\ApiFunction\UserSessionStatus::class,
        'rex_be_controller' => Backend\Controller::class,
        'rex_be_navigation' => Backend\Navigation::class,
        'rex_be_page' => Backend\Page::class,
        'rex_be_page_main' => Backend\MainPage::class,
        'rex_clang' => Language\Language::class,
        'rex_clang_perm' => Language\LanguagePermission::class,
        'rex_clang_service' => Language\LanguageHandler::class,
        'rex_console_application' => Console\Application::class,
        'rex_console_command' => Console\AbstractCommand::class,
        'rex_console_command_loader' => Console\CommandLoader::class,
        'rex_command_cache_clear' => Console\Command\CacheClearCommand::class,
        'rex_command_db_connection_options' => Database\Command\DatabaseConnectionOptionsCommand::class,
        'rex_command_db_dump_schema' => Database\Command\DatabaseDumpSchemaCommand::class,
        'rex_command_setup_check' => Console\Command\SystemCheckCommand::class,
        'rex_command_setup_run' => Setup\Command\SetupCommand::class,
        'rex_command_cronjob_run' => Cronjob\Command\CronjobRunCommand::class,
        'rex_command_package_list' => Addon\Command\AddonListCommand::class,
        'rex_command_system_report' => Console\Command\SystemReportCommand::class,
        'rex_command_user_create' => Security\Command\UserCreateCommand::class,
        'rex_command_user_delete' => Security\Command\UserDeleteCommand::class,
        'rex_command_user_list' => Security\Command\UserListCommand::class,
        'rex_command_user_set_password' => Security\Command\UserSetPasswordCommand::class,
        'rex_command_only_setup_packages' => Console\StandaloneInterface::class,
        'rex_command_standalone' => Console\StandaloneInterface::class,
        'rex_config' => Config::class,
        'rex_config_db' => Database\ConnectionConfig::class,
        'rex_context' => Http\Context::class,
        'rex_context_provider_interface' => Http\ContextProviderInterface::class,
        'rex_request' => Http\Request::class,
        'rex_response' => Http\Response::class,
        'rex_cronjob_manager_sql' => Cronjob\CronjobManager::class,
        'rex_dir' => Filesystem\Dir::class,
        'rex_editor' => Util\Editor::class,
        'rex_error_handler' => ErrorHandler::class,
        'rex_extension' => ExtensionPoint\Extension::class,
        'rex_extension_point' => ExtensionPoint\ExtensionPoint::class,
        'rex_extension_point_art_content_updated' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'rex_extension_point_console_shutdown' => Console\ExtensionPoint\ConsoleShutdown::class,
        'rex_extension_point_package_cache_deleted' => Addon\ExtensionPoint\AddonCacheDeleted::class,
        'rex_extension_point_slice_menu' => Content\ExtensionPoint\SliceMenu::class,
        'rex_file' => Filesystem\File::class,
        'rex_finder' => Filesystem\Finder::class,
        'rex_formatter' => Util\Formatter::class,
        'rex_i18n' => Translation\I18n::class,
        'rex_log_entry' => Log\LogEntry::class,
        'rex_log_file' => Log\LogFile::class,
        'rex_logger' => Log\Logger::class,
        'rex_markdown' => Util\Markdown\Markdown::class,
        'rex_mailer' => Mailer\Mailer::class,
        'rex_media' => MediaPool\Media::class,
        'rex_media_cache' => MediaPool\MediaPoolCache::class,
        'rex_media_category' => MediaPool\MediaCategory::class,
        'rex_media_service' => MediaPool\MediaHandler::class,
        'rex_media_category_service' => MediaPool\MediaCategoryHandler::class,
        'rex_media_perm' => MediaPool\MediaPoolPermission::class,
        'rex_mediapool' => MediaPool\MediaPool::class,
        'rex_media_manager' => MediaManager\MediaManager::class,
        'rex_pager' => Util\Pager::class,
        'rex_path' => Filesystem\Path::class,
        'rex_path_default_provider' => Filesystem\DefaultPathProvider::class,
        'rex_sortable_iterator' => Util\SortableIterator::class,
        'rex_sql' => Database\Sql::class,
        'rex_sql_column' => Database\Column::class,
        'rex_sql_foreign_key' => Database\ForeignKey::class,
        'rex_sql_index' => Database\Index::class,
        'rex_sql_schema_dumper' => Database\SchemaDumper::class,
        'rex_sql_table' => Database\Table::class,
        'rex_sql_util' => Database\DatabaseUtil::class,
        'rex_string' => Util\Str::class,
        'rex_timer' => Util\Timer::class,
        'rex_type' => Util\Type::class,
        'rex_var_dumper' => Util\VarDumper::class,
        'rex_factory_trait' => Base\FactoryTrait::class,
        'rex_instance_list_pool_trait' => Base\InstanceListPoolTrait::class,
        'rex_instance_pool_trait' => Base\InstancePoolTrait::class,
        'rex_singleton_trait' => Base\SingletonTrait::class,
        'rex_url' => Filesystem\Url::class,
        'rex_url_provider_interface' => Base\UrlProviderInterface::class,
        'rex_validator' => Validator\Validator::class,
        'rex_validation_rule' => Validator\ValidationRule::class,
        'rex_version' => Util\Version::class,
        'rex_article' => Content\Article::class,
        'rex_article_cache' => Content\ArticleCache::class,
        'rex_article_content' => Content\ArticleContent::class,
        'rex_article_content_base' => Content\ArticleContentBase::class,
        'rex_article_content_editor' => Content\ArticleContentEditor::class,
        'rex_article_revision' => Content\ArticleRevision::class,
        'rex_article_service' => Content\ArticleHandler::class,
        'rex_article_slice' => Content\ArticleSlice::class,
        'rex_article_slice_history' => Content\ArticleSliceHistory::class,
        'rex_category' => Content\Category::class,
        'rex_category_service' => Content\CategoryHandler::class,
        'rex_content_service' => Content\ContentHandler::class,
        'rex_ctype' => Content\ContentSection::class,
        'rex_history_login' => Content\HistoryLogin::class,
        'rex_linkmap_article_list' => Content\Linkmap\ArticleList::class,
        'rex_linkmap_article_list_renderer' => Content\Linkmap\ArticleListRenderer::class,
        'rex_linkmap_category_tree' => Content\Linkmap\CategoryTree::class,
        'rex_linkmap_tree_renderer' => Content\Linkmap\CategoryTreeRenderer::class,
        'rex_module' => Content\Module::class,
        'rex_module_perm' => Content\ModulePermission::class,
        'rex_structure_context' => Content\StructureContext::class,
        'rex_structure_element' => Content\StructureElement::class,
        'rex_structure_perm' => Content\StructurePermission::class,
        'rex_template' => Content\Template::class,
        'rex_backend_login' => Security\BackendLogin::class,
        'rex_backend_password_policy' => Security\BackendPasswordPolicy::class,
        'rex_complex_perm' => Security\ComplexPermission::class,
        'rex_csrf_token' => Security\CsrfToken::class,
        'rex_login' => Security\Login::class,
        'rex_login_policy' => Security\LoginPolicy::class,
        'rex_password_policy' => Security\PasswordPolicy::class,
        'rex_perm' => Security\Permission::class,
        'rex_user' => Security\User::class,
        'rex_user_role' => Security\UserRole::class,
        'rex_user_role_interface' => Security\UserRole::class,
        'rex_user_session' => Security\UserSession::class,
        'rex_webauthn' => Security\WebAuthn::class,
        'rex_setup' => Setup\Setup::class,
        'rex_list' => View\DataList::class,
        'rex_fragment' => View\Fragment::class,
        'rex_navigation' => Content\CategoryNavigation::class,
        'rex_system_report' => SystemReport::class,
        'rex_view' => View\View::class,
        'rex_article_not_found_exception' => Content\Exception\ArticleNotFoundException::class,
        'rex_exception' => Exception\Exception::class,
        'rex_functional_exception' => Exception\UserMessageException::class,
        'rex_http_exception' => Http\Exception\HttpException::class,
        'rex_media_manager_not_found_exception' => MediaManager\Exception\MediaNotFoundException::class,
        'rex_sql_exception' => Database\Exception\SqlException::class,
        'rex_sql_could_not_connect_exception' => Database\Exception\CouldNotConnectException::class,
    ])
    ->withConfiguredRule(RenameFunctionRector::class, [
        'rex_escape' => 'Redaxo\\Core\\View\\escape',
    ])
    ->withConfiguredRule(RenameMethodRector::class, [
        new MethodCallRename(Language\LanguageHandler::class, 'addCLang', 'add'),
        new MethodCallRename(Language\LanguageHandler::class, 'editCLang', 'edit'),
        new MethodCallRename(Language\LanguageHandler::class, 'deleteCLang', 'delete'),
        new MethodCallRename(Language\LanguagePermission::class, 'getClangs', 'getLanguageIds'),
        new MethodCallRename(View\View::class, 'clangSwitch', 'languageSwitch'),
        new MethodCallRename(View\View::class, 'clangSwitchAsButtons', 'languageSwitchAsButtons'),
        new MethodCallRename(View\View::class, 'clangSwitchAsDropdown', 'languageSwitchAsDropdown'),

        new MethodCallRename(Addon\Addon::class, 'getRegisteredPackages', 'getAll'),
        new MethodCallRename(Addon\Addon::class, 'getInstalledPackages', 'getAll'),
        new MethodCallRename(Addon\Addon::class, 'getAvailablePackages', 'getAll'),
        new MethodCallRename(Addon\Addon::class, 'getAvailableAddons', 'getAll'),

        new MethodCallRename(ApiFunction\Result::class, 'toJSON', 'toJson'),
        new MethodCallRename(ApiFunction\Result::class, 'fromJSON', 'fromJson'),

        new MethodCallRename(Security\PasswordPolicy::class, 'getRule', 'getDescription'),

        new MethodCallRename(Content\ArticleContentBase::class, 'getArticle', 'renderContent'),
        new MethodCallRename(Content\ArticleContentBase::class, 'getArticleTemplate', 'renderTemplate'),
        new MethodCallRename(Content\ArticleContentBase::class, 'getSlice', 'renderSlice'),
        new MethodCallRename(Content\ArticleSlice::class, 'getSlice', 'renderSlice'),
        new MethodCallRename(Content\Template::class, 'forKey', 'get'),
        new MethodCallRename(Content\Template::class, 'getCtypes', 'getContentSections'),
        new MethodCallRename(Content\Template::class, 'getDefaultId', 'getDefaultKey'),
        new MethodCallRename(Content\Module::class, 'forKey', 'getClass'),

        new MethodCallRename(Content\ArticleHandler::class, 'addArticle', 'add'),
        new MethodCallRename(Content\ArticleHandler::class, 'editArticle', 'edit'),
        new MethodCallRename(Content\ArticleHandler::class, 'deleteArticle', 'delete'),
        new MethodCallRename(Content\ArticleHandler::class, '_deleteArticle', 'deleteRecursively'),
        new MethodCallRename(Content\ArticleHandler::class, 'copyArticle', 'copy'),
        new MethodCallRename(Content\ArticleHandler::class, 'moveArticle', 'move'),
        new MethodCallRename(Content\ArticleHandler::class, 'articleStatus', 'changeStatus'),
        new MethodCallRename(Content\ArticleHandler::class, 'article2category', 'convertToCategory'),
        new MethodCallRename(Content\ArticleHandler::class, 'article2startarticle', 'convertToStartArticle'),
        new MethodCallRename(Content\ArticleHandler::class, 'newArtPrio', 'updatePriorities'),

        new MethodCallRename(Content\CategoryHandler::class, 'addCategory', 'add'),
        new MethodCallRename(Content\CategoryHandler::class, 'editCategory', 'edit'),
        new MethodCallRename(Content\CategoryHandler::class, 'deleteCategory', 'delete'),
        new MethodCallRename(Content\CategoryHandler::class, 'moveCategory', 'move'),
        new MethodCallRename(Content\CategoryHandler::class, 'categoryStatus', 'changeStatus'),
        new MethodCallRename(Content\CategoryHandler::class, 'newCatPrio', 'updatePriorities'),

        new MethodCallRename(Content\ContentHandler::class, 'sliceStatus', 'changeSliceStatus'),

        new MethodCallRename(MediaPool\MediaHandler::class, 'addMedia', 'add'),
        new MethodCallRename(MediaPool\MediaHandler::class, 'updateMedia', 'edit'),
        new MethodCallRename(MediaPool\MediaHandler::class, 'deleteMedia', 'delete'),

        new MethodCallRename(MediaPool\MediaCategoryHandler::class, 'addCategory', 'add'),
        new MethodCallRename(MediaPool\MediaCategoryHandler::class, 'editCategory', 'edit'),
        new MethodCallRename(MediaPool\MediaCategoryHandler::class, 'deleteCategory', 'delete'),
    ])
    ->withConfiguredRule(RedaxoRule\RenameStaticMethodRector::class, [
        new RenameStaticMethod(Core::class, 'getPackageOrder', Addon\Addon::class, 'getBootOrder'),
        new RenameStaticMethod(Core::class, 'getVersionHash', Util\Version::class, 'gitHash'),
        new RenameStaticMethod(Core::class, 'isDebugMode', Core::class, 'isDevMode'),
        new RenameStaticMethod(Core::class, 'isLiveMode', Core::class, 'isHardenedMode'),
        new RenameStaticMethod(Core::class, 'getServer', Filesystem\Url::class, 'absoluteBase'),
        new RenameStaticMethod(Core::class, 'getAccesskey', Backend\Accesskey::class, 'attributes'),
        new RenameStaticMethod(Core::class, 'getTheme', Backend\Appearance::class, 'getTheme'),
        new RenameStaticMethod(Core::class, 'getInstanceColor', Backend\Appearance::class, 'getInstanceColor'),

        new RenameStaticMethod(Security\BackendPasswordPolicy::class, 'factory', Security\BackendLogin::class, 'getPasswordPolicy'),
        new RenameStaticMethod(Security\Login::class, 'startSession', Http\Session::class, 'start'),
        new RenameStaticMethod(Security\Login::class, 'getCookieParams', Http\Session::class, 'getCookieParams'),
        new RenameStaticMethod(Http\Request::class, 'getSessionNamespace', Http\Session::class, 'getNamespace'),

        new RenameStaticMethod(ExtensionPoint\Extension::class, 'registerPoint', ExtensionPoint\Extension::class, 'dispatch'),
        new RenameStaticMethod(ExtensionPoint\Extension::class, 'isRegistered', ExtensionPoint\Extension::class, 'hasExtensions'),

        new RenameStaticMethod(Util\Str::class, 'versionSplit', Util\Version::class, 'split'),
        new RenameStaticMethod(Util\Str::class, 'versionCompare', Util\Version::class, 'compare'),

        new RenameStaticMethod(View\View::class, 'addCssFile', View\Asset::class, 'addCssFile'),
        new RenameStaticMethod(View\View::class, 'getCssFiles', View\Asset::class, 'getCssFiles'),
        new RenameStaticMethod(View\View::class, 'addJsFile', View\Asset::class, 'addJsFile'),
        new RenameStaticMethod(View\View::class, 'getJsFiles', View\Asset::class, 'getJsFiles'),
        new RenameStaticMethod(View\View::class, 'getJsFilesWithOptions', View\Asset::class, 'getJsFilesWithOptions'),
        new RenameStaticMethod(View\View::class, 'setJsProperty', View\Asset::class, 'setJsProperty'),
        new RenameStaticMethod(View\View::class, 'getJsProperties', View\Asset::class, 'getJsProperties'),
        new RenameStaticMethod(View\View::class, 'setFavicon', View\Asset::class, 'setFavicon'),
        new RenameStaticMethod(View\View::class, 'getFavicon', View\Asset::class, 'getFavicon'),
        new RenameStaticMethod(View\View::class, 'error', View\Message::class, 'error'),
        new RenameStaticMethod(View\View::class, 'info', View\Message::class, 'info'),
        new RenameStaticMethod(View\View::class, 'message', View\Message::class, 'message'),
        new RenameStaticMethod(View\View::class, 'success', View\Message::class, 'success'),
        new RenameStaticMethod(View\View::class, 'warning', View\Message::class, 'warning'),

        new RenameStaticMethod(Content\ArticleSlice::class, 'getFirstSliceForCtype', Content\ArticleSlice::class, 'getFirstSliceForContentSection'),
        new RenameStaticMethod(Content\ArticleHandler::class, 'category2article', Content\CategoryHandler::class, 'convertToArticle'),
        new RenameStaticMethod(Content\CategoryHandler::class, 'statusTypes', Content\ArticleHandler::class, 'statusTypes'),
        new RenameStaticMethod(Content\CategoryHandler::class, 'nextStatus', Content\ArticleHandler::class, 'nextStatus'),
        new RenameStaticMethod(Content\CategoryHandler::class, 'prevStatus', Content\ArticleHandler::class, 'prevStatus'),
    ])
    ->withConfiguredRule(RedaxoRule\NewToStaticCallRector::class, [
        new NewToStaticCall(Log\LogFile::class, Log\LogFile::class, 'factory'),
        new NewToStaticCall('rex_exception', Exception\RuntimeException::class, 'create'), // 2 step modification, see StaticCallToNewRector
    ])
    ->withConfiguredRule(StaticCallToNewRector::class, [
        new StaticCallToNew(Exception\RuntimeException::class, 'create'), // 2 step modification, see NewToStaticCallRector
    ])
    ->withConfiguredRule(RedaxoRule\MethodCallToStaticCallRector::class, [
        new MethodCallToStaticCall(Security\BackendLogin::class, 'getLoginPolicy', Security\BackendLogin::class, 'getLoginPolicy'),
    ])
    ->withConfiguredRule(RedaxoRule\FuncCallToStaticCallRector::class, [
        new FuncCallToStaticCall('rex_mediapool_filename', MediaPool\MediaPool::class, 'filename'),
        new FuncCallToStaticCall('rex_mediapool_isAllowedMediaType', MediaPool\MediaPool::class, 'isAllowedExtension'),
        new FuncCallToStaticCall('rex_mediapool_isAllowedMimeType', MediaPool\MediaPool::class, 'isAllowedMimeType'),
        new FuncCallToStaticCall('rex_mediapool_getMediaTypeWhitelist', MediaPool\MediaPool::class, 'getAllowedExtensions'),
        new FuncCallToStaticCall('rex_mediapool_getMediaTypeBlacklist', MediaPool\MediaPool::class, 'getBlockedExtensions'), // 2 step modification, see StaticCallToStaticPropertyFetchRector
        new FuncCallToStaticCall('rex_mediapool_Mediaform', View\View::class, 'mediaPoolMediaForm'),
        new FuncCallToStaticCall('rex_mediapool_Uploadform', View\View::class, 'mediaPoolMediaForm'),
        new FuncCallToStaticCall('rex_mediapool_Syncform', View\View::class, 'mediaPoolMediaForm'),

        // additional adjustments necessary afterward, see https://github.com/redaxo/core/pull/5918/files
        new FuncCallToStaticCall('rex_mediapool_saveMedia', MediaPool\MediaHandler::class, 'add'), // different params
        new FuncCallToStaticCall('rex_mediapool_updateMedia', MediaPool\MediaHandler::class, 'edit'), // different params
        new FuncCallToStaticCall('rex_mediapool_syncFile', MediaPool\MediaHandler::class, 'add'), // different params
        new FuncCallToStaticCall('rex_mediapool_deleteMedia', MediaPool\MediaHandler::class, 'delete'), // different return value

        new FuncCallToStaticCall('rex_cookie', Http\Request::class, 'cookie'),
        new FuncCallToStaticCall('rex_env', Http\Request::class, 'env'),
        new FuncCallToStaticCall('rex_files', Http\Request::class, 'files'),
        new FuncCallToStaticCall('rex_get', Http\Request::class, 'get'),
        new FuncCallToStaticCall('rex_post', Http\Request::class, 'post'),
        new FuncCallToStaticCall('rex_request', Http\Request::class, 'request'),
        new FuncCallToStaticCall('rex_request_method', Http\Request::class, 'requestMethod'),
        new FuncCallToStaticCall('rex_server', Http\Request::class, 'server'),
        new FuncCallToStaticCall('rex_session', Http\Request::class, 'session'),
        new FuncCallToStaticCall('rex_set_session', Http\Request::class, 'setSession'),
        new FuncCallToStaticCall('rex_unset_session', Http\Request::class, 'unsetSession'),

        new FuncCallToStaticCall('rex_getUrl', Filesystem\Url::class, 'article'),

        new FuncCallToStaticCall('rex_delete_cache', Cache::class, 'delete'),
    ])
    ->withConfiguredRule(RedaxoRule\MethodCallToPropertyFetchRector::class, [
        new MethodCallToPropertyFetch(Addon\Addon::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Addon\Addon::class, 'getPackageId', 'name'),

        new MethodCallToPropertyFetch(ApiFunction\ApiFunction::class, 'getResult', 'result'),
        new MethodCallToPropertyFetch(ApiFunction\ApiFunction::class, 'requiresCsrfProtection', 'requiresCsrfProtection'),

        new MethodCallToPropertyFetch(ApiFunction\Result::class, 'isSuccessfull', 'succeeded'),
        new MethodCallToPropertyFetch(ApiFunction\Result::class, 'getMessage', 'message'),
        new MethodCallToPropertyFetch(ApiFunction\Result::class, 'requiresReboot', 'requiresReboot'),

        new MethodCallToPropertyFetch(Console\AbstractCommand::class, 'getPackage', 'addon'),

        new MethodCallToPropertyFetch(Content\Article::class, 'getCategoryId', 'categoryId'), // changed from int to ?int
        new MethodCallToPropertyFetch(Content\Article::class, 'getTemplateId', 'templateKey'),
        new MethodCallToPropertyFetch(Content\Article::class, 'hasTemplate', 'templateKey'), // changed from bool to ?string, callers using the bool need manual adjustment

        new MethodCallToPropertyFetch(Content\ArticleContentBase::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\ArticleContentBase::class, 'getClang', 'languageId'),

        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getClang', 'languageId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getCtype', 'contentSectionId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getModuleId', 'moduleId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getRevision', 'revision'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getPriority', 'priority'),

        new MethodCallToPropertyFetch(Content\ContentSection::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Content\ContentSection::class, 'getName', 'name'),

        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getCategoryId', 'categoryId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getClangId', 'languageId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getCtypeId', 'contentSectionId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getArtStart', 'artStart'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getCatStart', 'catStart'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getEditId', 'editId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getFunction', 'function'),

        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getParentId', 'parentId'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getClang', 'languageId'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getPriority', 'priority'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getPath', 'path'), // changed from string to array, callers using the string need manual adjustment
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getPathAsArray', 'path'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getCreateDate', 'createDate'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getUpdateDate', 'updateDate'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getCreateUser', 'createUser'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getUpdateUser', 'updateUser'),

        new MethodCallToPropertyFetch(ExtensionPoint\ExtensionPoint::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(ExtensionPoint\ExtensionPoint::class, 'getSubject', 'subject'),
        new MethodCallToPropertyFetch(ExtensionPoint\ExtensionPoint::class, 'isReadonly', 'readonly'),
        new MethodCallToPropertyFetch(Console\ExtensionPoint\ConsoleShutdown::class, 'getCommand', 'command'),
        new MethodCallToPropertyFetch(Console\ExtensionPoint\ConsoleShutdown::class, 'getInput', 'input'),
        new MethodCallToPropertyFetch(Console\ExtensionPoint\ConsoleShutdown::class, 'getOutput', 'output'),
        new MethodCallToPropertyFetch(Console\ExtensionPoint\ConsoleShutdown::class, 'getExitCode', 'exitCode'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\ArticleContentUpdated::class, 'getArticle', 'article'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\ArticleContentUpdated::class, 'getAction', 'action'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getContext', 'context'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getFragment', 'fragment'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getClangId', 'languageId'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getCtypeId', 'contentSectionId'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getModuleId', 'moduleKey'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getSliceId', 'sliceId'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'hasPerm', 'hasPerm'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getMenuEditAction', 'menuEditAction'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getMenuDeleteAction', 'menuDeleteAction'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getMenuStatusAction', 'menuStatusAction'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getMenuMoveupAction', 'menuMoveupAction'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getMenuMovedownAction', 'menuMovedownAction'),
        new MethodCallToPropertyFetch(Content\ExtensionPoint\SliceMenu::class, 'getAdditionalActions', 'additionalActions'),

        new MethodCallToPropertyFetch(Database\Column::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Database\Column::class, 'getType', 'type'),
        new MethodCallToPropertyFetch(Database\Column::class, 'isNullable', 'nullable'),
        new MethodCallToPropertyFetch(Database\Column::class, 'getDefault', 'default'),
        new MethodCallToPropertyFetch(Database\Column::class, 'getExtra', 'extra'),
        new MethodCallToPropertyFetch(Database\Column::class, 'getComment', 'comment'),

        new MethodCallToPropertyFetch(Database\Index::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Database\Index::class, 'getColumns', 'columns'),
        new MethodCallToPropertyFetch(Database\Index::class, 'getType', 'type'),

        new MethodCallToPropertyFetch(Database\ForeignKey::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Database\ForeignKey::class, 'getTable', 'table'),
        new MethodCallToPropertyFetch(Database\ForeignKey::class, 'getColumns', 'columns'),
        new MethodCallToPropertyFetch(Database\ForeignKey::class, 'getOnUpdate', 'onUpdate'),
        new MethodCallToPropertyFetch(Database\ForeignKey::class, 'getOnDelete', 'onDelete'),

        new MethodCallToPropertyFetch(Database\Exception\SqlException::class, 'getSql', 'sql'),
        new MethodCallToPropertyFetch(Http\Exception\HttpException::class, 'getHttpCode', 'httpCode'),

        new MethodCallToPropertyFetch(Language\Language::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Language\Language::class, 'getCode', 'code'),
        new MethodCallToPropertyFetch(Language\Language::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Language\Language::class, 'getPriority', 'priority'),

        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getCategoryId', 'categoryId'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getFileName', 'fileName'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getOriginalFileName', 'originalFileName'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getType', 'type'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getSize', 'size'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getWidth', 'width'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getHeight', 'height'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getTitle', 'title'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getCreateDate', 'createDate'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getUpdateDate', 'updateDate'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getCreateUser', 'createUser'),
        new MethodCallToPropertyFetch(MediaPool\Media::class, 'getUpdateUser', 'updateUser'),

        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getParentId', 'parentId'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getPath', 'path'), // changed from string to array
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getPathAsArray', 'path'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getCreateDate', 'createDate'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getUpdateDate', 'updateDate'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getCreateUser', 'createUser'),
        new MethodCallToPropertyFetch(MediaPool\MediaCategory::class, 'getUpdateUser', 'updateUser'),

        new MethodCallToPropertyFetch(Security\BackendPasswordPolicy::class, 'getForceRenewAfter', 'forceRenewAfter'),
        new MethodCallToPropertyFetch(Security\BackendPasswordPolicy::class, 'getBlockAccountAfter', 'blockAccountAfter'),

        new MethodCallToPropertyFetch(Security\Login::class, 'getMessage', 'message'),

        new MethodCallToPropertyFetch(Security\LoginPolicy::class, 'getMaxTriesUntilDelay', 'maxTriesUntilDelay'),
        new MethodCallToPropertyFetch(Security\LoginPolicy::class, 'getMaxTriesUntilBlock', 'maxTriesUntilBlock'),
        new MethodCallToPropertyFetch(Security\LoginPolicy::class, 'getReloginDelay', 'reloginDelay'),
        new MethodCallToPropertyFetch(Security\LoginPolicy::class, 'isStayLoggedInEnabled', 'stayLoggedInEnabled'),

        new MethodCallToPropertyFetch(Security\CsrfToken::class, 'getId', 'id'),

        new MethodCallToPropertyFetch(Security\User::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Security\User::class, 'getLogin', 'login'),
        new MethodCallToPropertyFetch(Security\User::class, 'getName', 'name'),
        new MethodCallToPropertyFetch(Security\User::class, 'getEmail', 'email'),
        new MethodCallToPropertyFetch(Security\User::class, 'isAdmin', 'admin'),
        new MethodCallToPropertyFetch(Security\User::class, 'getLanguage', 'language'),
        new MethodCallToPropertyFetch(Security\User::class, 'getStartPage', 'startPage'),
    ])
    ->withConfiguredRule(RedaxoRule\MethodCallToPropertyAssignRector::class, [
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setSliceId', 'sliceId'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setSliceRevision', 'sliceRevision'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setMode', 'mode'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setFunction', 'function'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setEval', 'eval'),

        new MethodCallToPropertyAssign(Content\ExtensionPoint\SliceMenu::class, 'setMenuEditAction', 'menuEditAction'),
        new MethodCallToPropertyAssign(Content\ExtensionPoint\SliceMenu::class, 'setMenuDeleteAction', 'menuDeleteAction'),
        new MethodCallToPropertyAssign(Content\ExtensionPoint\SliceMenu::class, 'setMenuStatusAction', 'menuStatusAction'),
        new MethodCallToPropertyAssign(Content\ExtensionPoint\SliceMenu::class, 'setMenuMoveupAction', 'menuMoveupAction'),
        new MethodCallToPropertyAssign(Content\ExtensionPoint\SliceMenu::class, 'setMenuMovedownAction', 'menuMovedownAction'),
        new MethodCallToPropertyAssign(Content\ExtensionPoint\SliceMenu::class, 'setAdditionalActions', 'additionalActions'),

        new MethodCallToPropertyAssign(ExtensionPoint\ExtensionPoint::class, 'setSubject', 'subject'),

        new MethodCallToPropertyAssign(Security\Login::class, 'setCache', 'cache'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setSqlDb', 'DB'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setSystemId', 'systemId'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setSessionDuration', 'sessionDuration'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setUserQuery', 'userQuery'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setLoginQuery', 'loginQuery'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setImpersonateQuery', 'impersonateQuery'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setIdColumn', 'idColumn'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setPasswordColumn', 'passwordColumn'),
        new MethodCallToPropertyAssign(Security\Login::class, 'setMessage', 'message'),
    ])
    ->withConfiguredRule(RedaxoRule\StaticCallToStaticPropertyFetchRector::class, [
        new StaticCallToStaticPropertyFetch(MediaPool\MediaPool::class, 'getBlockedExtensions', 'blockedExtensions'),
        new StaticCallToStaticPropertyFetch(MediaPool\MediaPool::class, 'getAllowedMimeTypes', 'allowedMimeTypes'),
    ])
    ->withConfiguredRule(RedaxoRule\StaticCallToStaticPropertyAssignRector::class, [
        new StaticCallToStaticPropertyAssign(MediaPool\MediaPool::class, 'setAllowedMimeTypes', 'allowedMimeTypes'),
    ])
    ->withConfiguredRule(RedaxoRule\SetterCallToConstructorArgumentRector::class, [
        new SetterCallToConstructorArgument(ApiFunction\Result::class, 'setRequiresReboot', 'requiresReboot'),
    ])
    ->withConfiguredRule(RenamePropertyRector::class, [
        new RenameProperty(Content\ArticleContentBase::class, 'article_id', 'articleId'),
        new RenameProperty(Content\ArticleContentBase::class, 'clang', 'languageId'),
        new RenameProperty(Content\ArticleContentBase::class, 'slice_id', 'sliceId'),
        new RenameProperty(Content\ArticleContentBase::class, 'getSlice', 'singleSliceId'),
        new RenameProperty(Content\ArticleContentBase::class, 'ctype', 'contentSectionId'),
        new RenameProperty(Content\ArticleContentBase::class, 'slice_revision', 'sliceRevision'),
        new RenameProperty(Content\ArticleContentBase::class, 'warning', 'error'),
        new RenameProperty(Content\ArticleContentBase::class, 'info', 'success'),
    ])
    ->withConfiguredRule(ArgumentRemoverRector::class, [
        new ArgumentRemover(Util\Str::class, 'buildQuery', 1, null),
        new ArgumentRemover(Base\UrlProviderInterface::class, 'getUrl', 1, null),
        new ArgumentRemover(Filesystem\Url::class, 'frontendController', 1, null),
        new ArgumentRemover(Filesystem\Url::class, 'backendController', 1, null),
        new ArgumentRemover(Filesystem\Url::class, 'backendPage', 2, null),
        new ArgumentRemover(Filesystem\Url::class, 'currentBackendPage', 1, null),
        new ArgumentRemover(Filesystem\Url::class, 'article', 3, null),
        new ArgumentRemover(View\DataList::class, 'getUrl', 1, null),
        new ArgumentRemover(View\DataList::class, 'getParsedUrl', 1, null),
        new ArgumentRemover(Content\StructureElement::class, 'getUrl', 1, null),
        new ArgumentRemover(MediaManager\MediaManager::class, 'getUrl', 3, null),
    ])
    ->withConfiguredRule(RedaxoRule\ReplaceArgumentValueRector::class, [
        new ReplaceArgumentValue(Content\ArticleContentBase::class, 'renderContent', 0, -1, null),
        new ReplaceArgumentValue(Security\User::class, 'getComplexPerm', 0, 'clang', 'language'),
    ])
    ->withConfiguredRule(RedaxoRule\ExtensionPointNameToClassRector::class, [
        'PACKAGE_CACHE_DELETED' => Addon\ExtensionPoint\AddonCacheDeleted::class,
        'STRUCTURE_CONTENT_SLICE_ADDED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'STRUCTURE_CONTENT_SLICE_UPDATED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'STRUCTURE_CONTENT_SLICE_DELETED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'SLICE_ADDED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'SLICE_UPDATED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'SLICE_DELETED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'STRUCTURE_CONTENT_ARTICLE_UPDATED' => Content\ExtensionPoint\ArticleContentUpdated::class,
        'STRUCTURE_CONTENT_SLICE_MENU' => Content\ExtensionPoint\SliceMenu::class,
        'CLANG_ADDED' => Language\ExtensionPoint\LanguageAdded::class,
        'CLANG_UPDATED' => Language\ExtensionPoint\LanguageUpdated::class,
        'CLANG_DELETED' => Language\ExtensionPoint\LanguageDeleted::class,
        'CLANG_FORM_BUTTONS' => Language\ExtensionPoint\LanguageFormButtons::class,
        'CLANG_FORM_ADD' => Language\ExtensionPoint\LanguageFormAdd::class,
        'CLANG_FORM_EDIT' => Language\ExtensionPoint\LanguageFormEdit::class,
        'ART_ADDED' => Content\ExtensionPoint\ArticleAdded::class,
        'ART_UPDATED' => Content\ExtensionPoint\ArticleUpdated::class,
        'ART_META_UPDATED' => Content\ExtensionPoint\ArticleUpdated::class,
        'ART_DELETED' => Content\ExtensionPoint\ArticleDeleted::class,
        'CAT_ADDED' => Content\ExtensionPoint\CategoryAdded::class,
        'CAT_UPDATED' => Content\ExtensionPoint\CategoryUpdated::class,
        'CAT_DELETED' => Content\ExtensionPoint\CategoryDeleted::class,
        'MEDIA_ADDED' => MediaPool\ExtensionPoint\MediaAdded::class,
        'MEDIA_UPDATED' => MediaPool\ExtensionPoint\MediaUpdated::class,
        'MEDIA_MOVED' => MediaPool\ExtensionPoint\MediaUpdated::class,
        'MEDIA_DELETED' => MediaPool\ExtensionPoint\MediaDeleted::class,
        'MEDIA_CATEGORY_ADDED' => MediaPool\ExtensionPoint\MediaCategoryAdded::class,
        'MEDIA_CATEGORY_UPDATED' => MediaPool\ExtensionPoint\MediaCategoryUpdated::class,
        'MEDIA_CATEGORY_DELETED' => MediaPool\ExtensionPoint\MediaCategoryDeleted::class,
        'ART_STATUS' => Content\ExtensionPoint\ArticleStatusChanged::class,
        'CAT_STATUS' => Content\ExtensionPoint\CategoryStatusChanged::class,
        'ART_MOVED' => Content\ExtensionPoint\ArticleMoved::class,
        'CAT_MOVED' => Content\ExtensionPoint\CategoryMoved::class,
        'ART_COPIED' => Content\ExtensionPoint\ArticleCopied::class,
        'ART_TO_CAT' => Content\ExtensionPoint\ArticleConvertedToCategory::class,
        'CAT_TO_ART' => Content\ExtensionPoint\CategoryConvertedToArticle::class,
        'ART_TO_STARTARTICLE' => Content\ExtensionPoint\ArticleConvertedToStartArticle::class,
        'MEDIA_ADD' => MediaPool\ExtensionPoint\MediaAdding::class,
        'MEDIA_IS_IN_USE' => MediaPool\ExtensionPoint\MediaUsageCheck::class,
        'MEDIA_CATEGORY_IS_IN_USE' => MediaPool\ExtensionPoint\MediaCategoryUsageCheck::class,
        'SLICE_SHOW' => Content\ExtensionPoint\SliceOutput::class,
        'SLICE_OUTPUT' => Content\ExtensionPoint\SliceOutput::class,
        'SLICE_ADD' => Content\ExtensionPoint\SliceAdding::class,
        'SLICE_UPDATE' => Content\ExtensionPoint\SliceUpdating::class,
        'SLICE_MOVE' => Content\ExtensionPoint\SliceMoving::class,
        'SLICE_DELETE' => Content\ExtensionPoint\SliceDeleting::class,
        'ART_SLICES_COPY' => Content\ExtensionPoint\ArticleSlicesCopying::class,
        'ART_PRE_DELETED' => Content\ExtensionPoint\ArticleDeleting::class,
        'ART_IS_PERMITTED' => Content\ExtensionPoint\ArticlePermissionCheck::class,
        'CAT_IS_PERMITTED' => Content\ExtensionPoint\CategoryPermissionCheck::class,
        'MEDIA_IS_PERMITTED' => MediaPool\ExtensionPoint\MediaPermissionCheck::class,
        'ART_STATUS_TYPES' => Content\ExtensionPoint\ArticleStatusTypes::class,
        'CAT_STATUS_TYPES' => Content\ExtensionPoint\ArticleStatusTypes::class,
        'ART_CONTENT' => Content\ExtensionPoint\ArticleContentOutput::class,
        'ART_INIT' => Content\ExtensionPoint\ArticleContentRendering::class,
        'ART_SLICES_QUERY' => Content\ExtensionPoint\ArticleSlicesQuery::class,
        'URL_REWRITE' => Content\ExtensionPoint\ArticleUrl::class,
        'MEDIA_URL_REWRITE' => MediaPool\ExtensionPoint\MediaUrl::class,
        'MEDIA_LIST_QUERY' => MediaPool\ExtensionPoint\MediaListQuery::class,
        'MEDIA_MANAGER_URL' => MediaManager\ExtensionPoint\MediaManagerUrl::class,
        'STRUCTURE_CONTENT_HEADER' => Content\ExtensionPoint\ContentPageHeader::class,
        'STRUCTURE_CONTENT_BEFORE_SLICES' => Content\ExtensionPoint\ContentPageBeforeSlices::class,
        'STRUCTURE_CONTENT_AFTER_SLICES' => Content\ExtensionPoint\ContentPageAfterSlices::class,
        'STRUCTURE_CONTENT_SIDEBAR' => Content\ExtensionPoint\ContentPageSidebar::class,
        'STRUCTURE_CONTENT_MODULE_SELECT' => Content\ExtensionPoint\ContentPageModuleSelect::class,
        'SLICE_BE_PREVIEW' => Content\ExtensionPoint\SlicePreview::class,
        'CAT_FORM_ADD' => Content\ExtensionPoint\CategoryFormAdd::class,
        'CAT_FORM_EDIT' => Content\ExtensionPoint\CategoryFormEdit::class,
        'CAT_FORM_BUTTONS' => Content\ExtensionPoint\CategoryFormButtons::class,
        'PAGE_STRUCTURE_ARTICLE_ORDER_BY' => Content\ExtensionPoint\ArticleListOrder::class,
        'MEDIA_FORM_ADD' => MediaPool\ExtensionPoint\MediaFormAdd::class,
        'MEDIA_FORM_EDIT' => MediaPool\ExtensionPoint\MediaFormEdit::class,
        'MEDIA_DETAIL_SIDEBAR' => MediaPool\ExtensionPoint\MediaDetailSidebar::class,
        'MEDIA_LIST_FUNCTIONS' => MediaPool\ExtensionPoint\MediaListFunctions::class,
        'MEDIA_LIST_THUMBNAIL' => MediaPool\ExtensionPoint\MediaListThumbnail::class,
        'MEDIA_LIST_TOOLBAR' => MediaPool\ExtensionPoint\MediaListToolbar::class,
    ])
    ->withConfiguredRule(RenameClassConstFetchRector::class, [
        new RenameClassAndConstFetch(ExtensionPoint\Extension::class, 'EARLY', ExtensionPoint\ExtensionLevel::class, 'Early'),
        new RenameClassAndConstFetch(ExtensionPoint\Extension::class, 'NORMAL', ExtensionPoint\ExtensionLevel::class, 'Normal'),
        new RenameClassAndConstFetch(ExtensionPoint\Extension::class, 'LATE', ExtensionPoint\ExtensionLevel::class, 'Late'),

        new RenameClassAndConstFetch(View\View::class, 'JS_ASYNC', View\Asset::class, 'JS_ASYNC'),
        new RenameClassAndConstFetch(View\View::class, 'JS_DEFERED', View\Asset::class, 'JS_DEFERED'),
        new RenameClassAndConstFetch(View\View::class, 'JS_IMMUTABLE', View\Asset::class, 'JS_IMMUTABLE'),
    ])
;
