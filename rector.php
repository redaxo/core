<?php

declare(strict_types=1);

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\Arguments\Rector\ClassMethod\ReplaceArgumentDefaultValueRector;
use Rector\Arguments\ValueObject\ArgumentAdder;
use Rector\Arguments\ValueObject\ReplaceArgumentDefaultValue;
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
use Rector\Renaming\Rector\StaticCall\RenameStaticMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Rector\Renaming\ValueObject\RenameClassAndConstFetch;
use Rector\Renaming\ValueObject\RenameProperty;
use Rector\Renaming\ValueObject\RenameStaticMethod;
use Rector\Transform\Rector\ConstFetch\ConstFetchToClassConstFetchRector;
use Rector\Transform\Rector\FuncCall\FuncCallToStaticCallRector;
use Rector\Transform\Rector\MethodCall\MethodCallToStaticCallRector;
use Rector\Transform\Rector\New_\NewToStaticCallRector;
use Rector\Transform\Rector\StaticCall\StaticCallToNewRector;
use Rector\Transform\ValueObject\ConstFetchToClassConstFetch;
use Rector\Transform\ValueObject\FuncCallToStaticCall;
use Rector\Transform\ValueObject\MethodCallToStaticCall;
use Rector\Transform\ValueObject\NewToStaticCall;
use Rector\Transform\ValueObject\StaticCallToNew;
use Rector\TypeDeclaration\Rector as TypeDeclaration;
use Rector\ValueObject\PhpVersion;
use Redaxo\Core\Addon;
use Redaxo\Core\ApiFunction;
use Redaxo\Core\Backend;
use Redaxo\Core\Backup;
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
use Redaxo\Core\Form;
use Redaxo\Core\Http;
use Redaxo\Core\Language;
use Redaxo\Core\Log;
use Redaxo\Core\Mailer;
use Redaxo\Core\MediaManager;
use Redaxo\Core\MediaPool;
use Redaxo\Core\MetaInfo;
use Redaxo\Core\RexVar;
use Redaxo\Core\Security;
use Redaxo\Core\Setup;
use Redaxo\Core\SystemReport;
use Redaxo\Core\Translation;
use Redaxo\Core\Util;
use Redaxo\Core\Validator;
use Redaxo\Core\View;
use Redaxo\Rector\Rule as RedaxoRule;
use Redaxo\Rector\ValueObject\MethodCallToPropertyAssign;
use Redaxo\Rector\ValueObject\MethodCallToPropertyFetch;
use Redaxo\Rector\ValueObject\SetterCallToConstructorArgument;
use Redaxo\Rector\ValueObject\StaticCallToStaticPropertyAssign;
use Redaxo\Rector\ValueObject\StaticCallToStaticPropertyFetch;

return RectorConfig::configure()
    ->withPaths([
        '.tools/fixtures/',
        'addons',
        'boot/',
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

        // Composer script handler — uses Composer's runtime API (not in the dependency tree).
        __DIR__ . '/src/Composer/ScriptHandler.php',
    ])

    // Upgrade REDAXO 5 to 6
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
        'rex_api_package' => Addon\ApiFunction\AddonOperation::class,
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
        'rex_backup' => Backup\Backup::class,
        'rex_backup_file_compressor' => Backup\FileCompressor::class,
        'rex_backup_tar' => Backup\Tar::class,
        'rex_be_controller' => Backend\Controller::class,
        'rex_be_navigation' => Backend\Navigation::class,
        'rex_be_page' => Backend\Page::class,
        'rex_be_page_main' => Backend\MainPage::class,
        'rex_clang' => Language\Language::class,
        'rex_clang_perm' => Language\LanguagePermission::class,
        'rex_clang_service' => Language\LanguageHandler::class,
        'rex_console_application' => Console\Application::class,
        'rex_console_command' => Console\Command\AbstractCommand::class,
        'rex_console_command_loader' => Console\CommandLoader::class,
        'rex_command_cache_clear' => Console\Command\CacheClearCommand::class,
        'rex_command_config_get' => Console\Command\ConfigGetCommand::class,
        'rex_command_config_set' => Console\Command\ConfigSetCommand::class,
        'rex_command_db_connection_options' => Console\Command\DatabaseConnectionOptionsCommand::class,
        'rex_command_db_dump_schema' => Console\Command\DatabaseDumpSchemaCommand::class,
        'rex_command_db_set_connection' => Console\Command\DatabaseSetConnectionCommand::class,
        'rex_command_setup_check' => Console\Command\SetupCheckCommand::class,
        'rex_command_setup_run' => Console\Command\SetupRunCommand::class,
        'rex_command_assets_sync' => Console\Command\AssetsSyncCommand::class,
        'rex_command_cronjob_run' => Console\Command\CronjobRunCommand::class,
        'rex_command_list' => Console\Command\ListCommand::class,
        'rex_command_package_activate' => Console\Command\AddonActivateCommand::class,
        'rex_command_package_deactivate' => Console\Command\AddonDeactivateCommand::class,
        'rex_command_package_list' => Console\Command\AddonListCommand::class,
        'rex_command_package_install' => Console\Command\AddonInstallCommand::class,
        'rex_command_package_uninstall' => Console\Command\AddonUninstallCommand::class,
        'rex_command_system_report' => Console\Command\SystemReportCommand::class,
        'rex_command_user_create' => Console\Command\UserCreateCommand::class,
        'rex_command_user_delete' => Console\Command\UserDeleteCommand::class,
        'rex_command_user_list' => Console\Command\UserListCommand::class,
        'rex_command_user_set_password' => Console\Command\UserSetPasswordCommand::class,
        'rex_command_only_setup_packages' => Console\Command\OnlySetupAddonsInterface::class,
        'rex_command_standalone' => Console\Command\StandaloneInterface::class,
        'rex_cronjob_form' => Cronjob\Form\CronjobForm::class,
        'rex_config' => Config::class,
        'rex_config_db' => Database\Configuration::class,
        'rex_context' => Http\Context::class,
        'rex_context_provider_interface' => Http\ContextProviderInterface::class,
        'rex_request' => Http\Request::class,
        'rex_response' => Http\Response::class,
        'rex_cronjob_form_interval_element' => Cronjob\Form\IntervalField::class,
        'rex_cronjob' => Cronjob\Type\AbstractType::class,
        'rex_cronjob_urlrequest' => Cronjob\Type\UrlRequestType::class,
        'rex_cronjob_article_status' => Cronjob\Type\ArticleStatusType::class,
        'rex_cronjob_optimize_tables' => Cronjob\Type\OptimizeTableType::class,
        'rex_cronjob_export' => Cronjob\Type\ExportType::class,
        'rex_cronjob_structure_history' => Cronjob\Type\ClearArticleHistoryType::class,
        'rex_cronjob_mailer_purge' => Cronjob\Type\PurgeMailerArchiveType::class,
        'rex_cronjob_manager' => Cronjob\CronjobExecutor::class,
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
        'rex_form_base' => Form\AbstractForm::class,
        'rex_form' => Form\Form::class,
        'rex_config_form' => Form\ConfigForm::class,
        'rex_form_element' => Form\Field\BaseField::class,
        'rex_form_options_element' => Form\Field\AbstractOptionField::class,
        'rex_form_checkbox_element' => Form\Field\CheckboxField::class,
        'rex_form_radio_element' => Form\Field\RadioField::class,
        'rex_form_container_element' => Form\Field\ContainerField::class,
        'rex_form_control_element' => Form\Field\ControlField::class,
        'rex_form_select_element' => Form\Field\SelectField::class,
        'rex_form_prio_element' => Form\Field\PriorityField::class,
        'rex_form_perm_select_element' => Form\Field\PermissionSelectField::class,
        'rex_form_raw_element' => Form\Field\RawField::class,
        'rex_form_widget_linkmap_element' => Form\Field\ArticleField::class,
        'rex_form_widget_media_element' => Form\Field\MediaField::class,
        'rex_select' => Form\Select\Select::class,
        'rex_category_select' => Form\Select\CategorySelect::class,
        'rex_media_category_select' => Form\Select\MediaCategorySelect::class,
        'rex_template_select' => Form\Select\TemplateSelect::class,
        'rex_formatter' => Util\Formatter::class,
        'rex_i18n' => Translation\I18n::class,
        'rex_log_entry' => Log\LogEntry::class,
        'rex_log_file' => Log\LogFile::class,
        'rex_logger' => Log\Logger::class,
        'rex_markdown' => Util\Markdown::class,
        'rex_mailer' => Mailer\Mailer::class,
        'rex_media' => MediaPool\Media::class,
        'rex_media_cache' => MediaPool\MediaPoolCache::class,
        'rex_media_category' => MediaPool\MediaCategory::class,
        'rex_media_service' => MediaPool\MediaHandler::class,
        'rex_media_category_service' => MediaPool\MediaCategoryHandler::class,
        'rex_media_perm' => MediaPool\MediaPoolPermission::class,
        'rex_mediapool' => MediaPool\MediaPool::class,
        'rex_media_manager' => MediaManager\MediaManager::class,
        'rex_metainfo_handler' => MetaInfo\Handler\AbstractHandler::class,
        'rex_metainfo_article_handler' => MetaInfo\Handler\ArticleHandler::class,
        'rex_metainfo_category_handler' => MetaInfo\Handler\CategoryHandler::class,
        'rex_metainfo_clang_handler' => MetaInfo\Handler\LanguageHandler::class,
        'rex_metainfo_media_handler' => MetaInfo\Handler\MediaHandler::class,
        'rex_pager' => Util\Pager::class,
        'rex_parsedown' => Util\Parsedown::class,
        'rex_path' => Filesystem\Path::class,
        'rex_path_default_provider' => Filesystem\DefaultPathProvider::class,
        'rex_sortable_iterator' => Util\SortableIterator::class,
        'rex_sql' => Database\Sql::class,
        'rex_sql_column' => Database\Column::class,
        'rex_sql_foreign_key' => Database\ForeignKey::class,
        'rex_sql_index' => Database\Index::class,
        'rex_sql_schema_dumper' => Database\SchemaDumper::class,
        'rex_sql_table' => Database\Table::class,
        'rex_sql_util' => Database\Util::class,
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
        'rex_var_link' => RexVar\LinkVar::class,
        'rex_var_linklist' => RexVar\LinkListVar::class,
        'rex_var_media' => RexVar\MediaVar::class,
        'rex_var_medialist' => RexVar\MediaListVar::class,
        'rex_version' => Util\Version::class,
        'rex_article' => Content\Article::class,
        'rex_article_action' => Content\ArticleSliceAction::class,
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
        'rex_setup_importer' => Setup\Importer::class,
        'rex_list' => View\DataList::class,
        'rex_fragment' => View\Fragment::class,
        'rex_navigation' => View\Navigation::class,
        'rex_system_report' => SystemReport::class,
        'rex_view' => View\View::class,
        'rex_article_not_found_exception' => Content\Exception\ArticleNotFoundException::class,
        'rex_exception' => Exception\Exception::class,
        'rex_functional_exception' => Exception\UserMessageException::class,
        'rex_http_exception' => Http\Exception\HttpException::class,
        'rex_media_manager_not_found_exception' => MediaManager\Exception\MediaNotFoundException::class,
        'rex_sql_exception' => Database\Exception\SqlException::class,
        'rex_sql_could_not_connect_exception' => Database\Exception\CouldNotConnectException::class,
        'rex_yaml_parse_exception' => Util\Exception\YamlParseException::class,
    ])
    ->withConfiguredRule(RenameFunctionRector::class, [
        'rex_escape' => 'Redaxo\\Core\\View\\escape',
    ])
    ->withConfiguredRule(ArgumentAdderRector::class, [
        new ArgumentAdder(Form\AbstractForm::class, 'addLinklistField', 1, 'value', null),
        new ArgumentAdder(Form\AbstractForm::class, 'addLinklistField', 2, 'arguments', ['multiple' => true]),
        new ArgumentAdder(Form\AbstractForm::class, 'addMedialistField', 1, 'value', null),
        new ArgumentAdder(Form\AbstractForm::class, 'addMedialistField', 2, 'arguments', ['multiple' => true]),
    ])
    ->withConfiguredRule(RenameMethodRector::class, [
        new MethodCallRename(Addon\Addon::class, 'getRegisteredPackages', 'getRegisteredAddons'),
        new MethodCallRename(Addon\Addon::class, 'getInstalledPackages', 'getInstalledAddons'),
        new MethodCallRename(Addon\Addon::class, 'getAvailablePackages', 'getActivatedAddons'),
        new MethodCallRename(Addon\Addon::class, 'getAvailableAddons', 'getActivatedAddons'),
        new MethodCallRename(Addon\Addon::class, 'getSetupPackages', 'getSetupAddons'),
        new MethodCallRename(Addon\Addon::class, 'isAvailable', 'isActivated'),

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
        new MethodCallRename(Content\Module::class, 'forKey', 'get'),

        new MethodCallRename(Mailer\Mailer::class, 'setLog', 'setArchive'),

        new MethodCallRename(Form\AbstractForm::class, 'addLinklistField', 'addArticleField'),
        new MethodCallRename(Form\AbstractForm::class, 'addLinkmapField', 'addArticleField'),
        new MethodCallRename(Form\AbstractForm::class, 'addMedialistField', 'addMediaField'),

        new MethodCallRename(Cronjob\CronjobManager::class, 'getManager', 'getExecutor'),
        new MethodCallRename(Cronjob\CronjobManager::class, 'hasManager', 'hasExecutor'),
    ])
    ->withConfiguredRule(RenameStaticMethodRector::class, [
        new RenameStaticMethod(Core::class, 'getPackageConfig', Addon\AddonManager::class, 'getAddonConfig'),
        new RenameStaticMethod(Core::class, 'getPackageOrder', Addon\AddonManager::class, 'getAddonOrder'),
        new RenameStaticMethod(Core::class, 'getVersionHash', Util\Version::class, 'gitHash'),
        new RenameStaticMethod(Core::class, 'isDebugMode', Core::class, 'isDevMode'),
        new RenameStaticMethod(Core::class, 'isLiveMode', Core::class, 'isHardenedMode'),
        new RenameStaticMethod(Core::class, 'getAccesskey', Backend\Accesskey::class, 'attributes'),
        new RenameStaticMethod(Core::class, 'getTheme', Backend\Appearance::class, 'getTheme'),
        new RenameStaticMethod(Core::class, 'getInstanceColor', Backend\Appearance::class, 'getInstanceColor'),

        new RenameStaticMethod(Security\BackendPasswordPolicy::class, 'factory', Security\BackendLogin::class, 'getPasswordPolicy'),

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
    ])
    ->withConfiguredRule(NewToStaticCallRector::class, [
        new NewToStaticCall(Log\LogFile::class, Log\LogFile::class, 'factory'),
        new NewToStaticCall('rex_exception', Exception\RuntimeException::class, 'create'), // 2 step modification, see StaticCallToNewRector
    ])
    ->withConfiguredRule(StaticCallToNewRector::class, [
        new StaticCallToNew(Exception\RuntimeException::class, 'create'), // 2 step modification, see NewToStaticCallRector
    ])
    ->withConfiguredRule(MethodCallToStaticCallRector::class, [
        new MethodCallToStaticCall(Security\BackendLogin::class, 'getLoginPolicy', Security\BackendLogin::class, 'getLoginPolicy'),
    ])
    ->withConfiguredRule(FuncCallToStaticCallRector::class, [
        new FuncCallToStaticCall('rex_mediapool_filename', MediaPool\MediaPool::class, 'filename'),
        new FuncCallToStaticCall('rex_mediapool_mediaIsInUse', MediaPool\MediaPool::class, 'mediaIsInUse'),
        new FuncCallToStaticCall('rex_mediapool_isAllowedMediaType', MediaPool\MediaPool::class, 'isAllowedExtension'),
        new FuncCallToStaticCall('rex_mediapool_isAllowedMimeType', MediaPool\MediaPool::class, 'isAllowedMimeType'),
        new FuncCallToStaticCall('rex_mediapool_getMediaTypeWhitelist', MediaPool\MediaPool::class, 'getAllowedExtensions'),
        new FuncCallToStaticCall('rex_mediapool_getMediaTypeBlacklist', MediaPool\MediaPool::class, 'getBlockedExtensions'), // 2 step modification, see StaticCallToStaticPropertyFetchRector
        new FuncCallToStaticCall('rex_mediapool_Mediaform', View\View::class, 'mediaPoolMediaForm'),
        new FuncCallToStaticCall('rex_mediapool_Uploadform', View\View::class, 'mediaPoolMediaForm'),
        new FuncCallToStaticCall('rex_mediapool_Syncform', View\View::class, 'mediaPoolMediaForm'),

        // additional adjustments necessary afterward, see https://github.com/redaxo/core/pull/5918/files
        new FuncCallToStaticCall('rex_mediapool_saveMedia', MediaPool\MediaPool::class, 'addMedia'), // different params
        new FuncCallToStaticCall('rex_mediapool_updateMedia', MediaPool\MediaPool::class, 'updateMedia'), // different params
        new FuncCallToStaticCall('rex_mediapool_syncFile', MediaPool\MediaPool::class, 'addMedia'), // different params
        new FuncCallToStaticCall('rex_mediapool_deleteMedia', MediaPool\MediaPool::class, 'deleteMedia'), // different return value

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

        new MethodCallToPropertyFetch(Console\Command\AbstractCommand::class, 'getPackage', 'addon'),

        new MethodCallToPropertyFetch(Cronjob\Type\AbstractType::class, 'getMessage', 'message'),
        new MethodCallToPropertyFetch(Cronjob\Type\AbstractType::class, 'hasMessage', 'message'),
        new MethodCallToPropertyFetch(Cronjob\CronjobExecutor::class, 'getMessage', 'message'),
        new MethodCallToPropertyFetch(Cronjob\CronjobExecutor::class, 'hasMessage', 'message'),

        new MethodCallToPropertyFetch(Content\Article::class, 'getCategoryId', 'categoryId'), // changed from int to ?int
        new MethodCallToPropertyFetch(Content\Article::class, 'getTemplateId', 'templateKey'),
        new MethodCallToPropertyFetch(Content\Article::class, 'hasTemplate', 'templateKey'), // changed from bool to ?string, callers using the bool need manual adjustment

        new MethodCallToPropertyFetch(Content\ArticleContentBase::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\ArticleContentBase::class, 'getClang', 'clangId'),

        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getClang', 'clangId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getCtype', 'contentSectionId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getModuleId', 'moduleId'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getRevision', 'revision'),
        new MethodCallToPropertyFetch(Content\ArticleSlice::class, 'getPriority', 'priority'),

        new MethodCallToPropertyFetch(Content\ArticleSliceAction::class, 'getEvent', 'mode'),
        new MethodCallToPropertyFetch(Content\ArticleSliceAction::class, 'getSave', 'save'),
        new MethodCallToPropertyFetch(Content\ArticleSliceAction::class, 'getMessages', 'messages'),

        new MethodCallToPropertyFetch(Content\ContentSection::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Content\ContentSection::class, 'getName', 'name'),

        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getCategoryId', 'categoryId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getArticleId', 'articleId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getClangId', 'clangId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getCtypeId', 'ctypeId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getArtStart', 'artStart'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getCatStart', 'catStart'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getEditId', 'editId'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getFunction', 'function'),
        new MethodCallToPropertyFetch(Content\StructureContext::class, 'getRowsPerPage', 'rowsPerPage'),

        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getId', 'id'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getParentId', 'parentId'),
        new MethodCallToPropertyFetch(Content\StructureElement::class, 'getClang', 'clangId'),
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
        new MethodCallToPropertyAssign(Cronjob\Type\AbstractType::class, 'setMessage', 'message'),
        new MethodCallToPropertyAssign(Cronjob\CronjobExecutor::class, 'setMessage', 'message'),

        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setSliceId', 'sliceId'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setSliceRevision', 'sliceRevision'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setMode', 'mode'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setFunction', 'function'),
        new MethodCallToPropertyAssign(Content\ArticleContentBase::class, 'setEval', 'eval'),

        new MethodCallToPropertyAssign(Content\ArticleSliceAction::class, 'setSave', 'save'),

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
        new RenameProperty(Content\ArticleContentBase::class, 'clang', 'clangId'),
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
        new ArgumentRemover(Form\AbstractForm::class, 'getUrl', 1, null),
        new ArgumentRemover(View\DataList::class, 'getUrl', 1, null),
        new ArgumentRemover(View\DataList::class, 'getParsedUrl', 1, null),
        new ArgumentRemover(Content\StructureElement::class, 'getUrl', 1, null),
        new ArgumentRemover(MediaManager\MediaManager::class, 'getUrl', 3, null),

        new ArgumentRemover(Util\Markdown::class, 'parse', 1, [true]),
        new ArgumentRemover(Util\Markdown::class, 'parseWithToc', 3, [true]),
    ])
    ->withConfiguredRule(ReplaceArgumentDefaultValueRector::class, [
        new ReplaceArgumentDefaultValue(Content\ArticleContentBase::class, 'renderContent', 0, -1, null),

        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'PACKAGE_CACHE_DELETED', 'ADDON_CACHE_DELETED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_ADDED', 'SLICE_ADDED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_UPDATED', 'SLICE_UPDATED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_DELETED', 'SLICE_DELETED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_MENU', '\\' . Content\ExtensionPoint\SliceMenu::class . '::NAME'),

        new ReplaceArgumentDefaultValue(Form\Select\CategorySelect::class, '__construct', 1, false, null),

        new ReplaceArgumentDefaultValue(Util\Markdown::class, 'parse', 1, false, $options = [
            new ArrayItem(new Expr\ConstFetch(new Name('false')), new Expr\ClassConstFetch(new Name(Util\Markdown::class), 'SOFT_LINE_BREAKS')),
        ]),
        new ReplaceArgumentDefaultValue(Util\Markdown::class, 'parseWithToc', 3, false, $options),
    ])
    ->withConfiguredRule(ConstFetchToClassConstFetchRector::class, [
        new ConstFetchToClassConstFetch('REX_FORM_ERROR_VIOLATE_UNIQUE_KEY', Form\Form::class, 'ERROR_VIOLATE_UNIQUE_KEY'),
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
