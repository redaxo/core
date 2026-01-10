<?php

declare(strict_types=1);

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
use Rector\Renaming\Rector\StaticCall\RenameStaticMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Rector\Renaming\ValueObject\RenameClassAndConstFetch;
use Rector\Renaming\ValueObject\RenameClassConstFetch;
use Rector\Renaming\ValueObject\RenameStaticMethod;
use Rector\Transform\Rector\ConstFetch\ConstFetchToClassConstFetchRector;
use Rector\Transform\Rector\FuncCall\FuncCallToStaticCallRector;
use Rector\Transform\Rector\New_\NewToStaticCallRector;
use Rector\Transform\Rector\StaticCall\StaticCallToNewRector;
use Rector\Transform\ValueObject\ConstFetchToClassConstFetch;
use Rector\Transform\ValueObject\FuncCallToStaticCall;
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
use Redaxo\Core\HttpClient;
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

return RectorConfig::configure()
    ->withPaths([
        // restrict to core and core addons, ignore other locally installed addons
        '.tools/project/',
        'assets_src/vendor_files.php',
        'boot/',
        'fragments/',
        'pages/',
        'setup/',
        'src/',
        'tests/',
        'redaxo/src/addons/debug/',
        'redaxo/src/addons/install/',
    ])
    ->withParallel()
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPreparedSets(typeDeclarations: false, privatization: true)
    // ->withPhpSets()
    ->withImportNames()
    ->withRules([
        CodeQuality\Assign\CombinedAssignRector::class,
        CodeQuality\BooleanNot\SimplifyDeMorganBinaryRector::class,
        CodeQuality\Class_\InlineConstructorDefaultToPropertyRector::class,
        CodeQuality\Class_\ConvertStaticToSelfRector::class,
        CodeQuality\Foreach_\SimplifyForeachToCoalescingRector::class,
        CodeQuality\FuncCall\SimplifyRegexPatternRector::class,
        CodeQuality\FuncCall\SingleInArrayToCompareRector::class,
        CodeQuality\Identical\SimplifyBoolIdenticalTrueRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\If_\SimplifyIfReturnBoolRector::class,
        CodeQuality\NullsafeMethodCall\CleanupUnneededNullsafeOperatorRector::class,
        CodeQuality\Ternary\UnnecessaryTernaryExpressionRector::class,
        CodingStyle\ClassConst\RemoveFinalFromConstRector::class,
        // CodingStyle\String_\SymplifyQuoteEscapeRector::class,
        Php55\ClassConstFetch\StaticToSelfOnFinalClassRector::class,
        Php70\StmtsAwareInterface\IfIssetToCoalescingRector::class,
        Php70\Ternary\TernaryToNullCoalescingRector::class,
        Php80\Catch_\RemoveUnusedVariableInCatchRector::class,
        Php80\Identical\StrEndsWithRector::class,
        Php80\Identical\StrStartsWithRector::class,
        Php80\NotIdentical\StrContainsRector::class,
        Php80\Switch_\ChangeSwitchToMatchRector::class,
        Php81\Array_\ArrayToFirstClassCallableRector::class,
        // Php81\Property\ReadOnlyPropertyRector::class,
        // Php82\Class_\ReadOnlyClassRector::class,
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
    ->withConfiguredRule(RenameClassRector::class, [
        'rex' => Core::class,
        'rex_package_interface' => Addon\AddonInterface::class,
        'rex_null_package' => Addon\NullAddon::class,
        'rex_package' => Addon\Addon::class,
        'rex_package_manager' => Addon\AddonManager::class,
        'rex_addon' => Addon\Addon::class,
        'rex_addon_interface' => Addon\AddonInterface::class,
        'rex_addon_manager' => Addon\AddonManager::class,
        'rex_null_addon' => Addon\NullAddon::class,
        'rex_api_exception' => ApiFunction\Exception\ApiFunctionException::class,
        'rex_api_function' => ApiFunction\ApiFunction::class,
        'rex_api_result' => ApiFunction\Result::class,
        'rex_api_metainfo_default_fields_create' => MetaInfo\ApiFunction\DefaultFieldsCreate::class,
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
        'rex_command_be_style_compile' => Console\Command\AssetsCompileStylesCommand::class,
        'rex_command_cronjob_run' => Console\Command\CronjobRunCommand::class,
        'rex_command_list' => Console\Command\ListCommand::class,
        'rex_command_package_activate' => Console\Command\AddonActivateCommand::class,
        'rex_command_package_deactivate' => Console\Command\AddonDeactivateCommand::class,
        'rex_command_package_delete' => Console\Command\AddonDeleteCommand::class,
        'rex_command_package_list' => Console\Command\AddonListCommand::class,
        'rex_command_package_install' => Console\Command\AddonInstallCommand::class,
        'rex_command_package_run_update_script' => Console\Command\AddonRunUpdateScriptCommand::class,
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
        'rex_form_restrictons_element' => MetaInfo\Form\Field\RestrictionField::class,
        'rex_form_raw_element' => Form\Field\RawField::class,
        'rex_form_widget_linkmap_element' => Form\Field\ArticleField::class,
        'rex_form_widget_media_element' => Form\Field\MediaField::class,
        'rex_select' => Form\Select\Select::class,
        'rex_event_select' => Form\Select\ActionEventSelect::class,
        'rex_category_select' => Form\Select\CategorySelect::class,
        'rex_media_category_select' => Form\Select\MediaCategorySelect::class,
        'rex_template_select' => Form\Select\TemplateSelect::class,
        'rex_formatter' => Util\Formatter::class,
        'rex_i18n' => Translation\I18n::class,
        'rex_input' => MetaInfo\Form\Input\AbstractInput::class,
        'rex_input_date' => MetaInfo\Form\Input\DateInput::class,
        'rex_input_datetime' => MetaInfo\Form\Input\DateTimeInput::class,
        'rex_input_linkbutton' => MetaInfo\Form\Input\ArticleInput::class,
        'rex_input_mediabutton' => MetaInfo\Form\Input\MediaInput::class,
        'rex_input_select' => MetaInfo\Form\Input\SelectInput::class,
        'rex_input_text' => MetaInfo\Form\Input\TextInput::class,
        'rex_input_textarea' => MetaInfo\Form\Input\TextareaInput::class,
        'rex_input_time' => MetaInfo\Form\Input\TimeInput::class,
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
        'rex_managed_media' => MediaManager\ManagedMedia::class,
        'rex_effect_abstract' => MediaManager\Effect\AbstractEffect::class,
        'rex_effect_convert2img' => MediaManager\Effect\ConvertToImageEffect::class,
        'rex_effect_crop' => MediaManager\Effect\CropEffect::class,
        'rex_effect_filter_blur' => MediaManager\Effect\FilterBlurEffect::class,
        'rex_effect_filter_brightness' => MediaManager\Effect\FilterBrightnessEffect::class,
        'rex_effect_filter_colorize' => MediaManager\Effect\FilterColorizeEffect::class,
        'rex_effect_filter_contrast' => MediaManager\Effect\FilterContrastEffect::class,
        'rex_effect_filter_greyscale' => MediaManager\Effect\FilterGreyscaleEffect::class,
        'rex_effect_filter_sepia' => MediaManager\Effect\FilterSepiaEffect::class,
        'rex_effect_filter_sharpen' => MediaManager\Effect\FilterSharpenEffect::class,
        'rex_effect_flip' => MediaManager\Effect\FlipEffect::class,
        'rex_effect_header' => MediaManager\Effect\HeaderEffect::class,
        'rex_effect_image_format' => MediaManager\Effect\ImageFormatEffect::class,
        'rex_effect_image_properties' => MediaManager\Effect\ImagePropertiesEffect::class,
        'rex_effect_insert_image' => MediaManager\Effect\InsertImageEffect::class,
        'rex_effect_mediapath' => MediaManager\Effect\MediaPathEffect::class,
        'rex_effect_mirror' => MediaManager\Effect\MirrorEffect::class,
        'rex_effect_resize' => MediaManager\Effect\ResizeEffect::class,
        'rex_effect_rotate' => MediaManager\Effect\RotateEffect::class,
        'rex_effect_rounded_corners' => MediaManager\Effect\RoundedCornersEffect::class,
        'rex_effect_workspace' => MediaManager\Effect\WorkspaceEffect::class,
        'rex_metainfo_default_type' => MetaInfo\Form\DefaultType::class,
        'rex_metainfo_handler' => MetaInfo\Handler\AbstractHandler::class,
        'rex_metainfo_article_handler' => MetaInfo\Handler\ArticleHandler::class,
        'rex_metainfo_category_handler' => MetaInfo\Handler\CategoryHandler::class,
        'rex_metainfo_clang_handler' => MetaInfo\Handler\LanguageHandler::class,
        'rex_metainfo_media_handler' => MetaInfo\Handler\MediaHandler::class,
        'rex_metainfo_table_expander' => MetaInfo\Form\MetaInfoForm::class,
        'rex_metainfo_table_manager' => MetaInfo\Database\Table::class,
        'rex_pager' => Util\Pager::class,
        'rex_parsedown' => Util\Parsedown::class,
        'rex_path' => Filesystem\Path::class,
        'rex_path_default_provider' => Filesystem\DefaultPathProvider::class,
        'rex_socket' => HttpClient\Request::class,
        'rex_socket_response' => HttpClient\Response::class,
        'rex_socket_proxy' => HttpClient\ProxyRequest::class,
        'rex_sortable_iterator' => Util\SortableIterator::class,
        'rex_sql' => Database\Sql::class,
        'rex_sql_column' => Database\Column::class,
        'rex_sql_foreign_key' => Database\ForeignKey::class,
        'rex_sql_index' => Database\Index::class,
        'rex_sql_schema_dumper' => Database\SchemaDumper::class,
        'rex_sql_table' => Database\Table::class,
        'rex_sql_util' => Database\Util::class,
        'rex_stream' => Util\Stream::class,
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
        'rex_var' => RexVar\RexVar::class,
        'rex_var_article' => RexVar\ArticleVar::class,
        'rex_var_category' => RexVar\CategoryVar::class,
        'rex_var_clang' => RexVar\LanguageVar::class,
        'rex_var_config' => RexVar\ConfigVar::class,
        'rex_var_link' => RexVar\LinkVar::class,
        'rex_var_linklist' => RexVar\LinkListVar::class,
        'rex_var_media' => RexVar\MediaVar::class,
        'rex_var_medialist' => RexVar\MediaListVar::class,
        'rex_var_property' => RexVar\PropertyVar::class,
        'rex_var_template' => RexVar\TemplateVar::class,
        'rex_var_value' => RexVar\ValueVar::class,
        'rex_version' => Util\Version::class,
        'rex_article' => Content\Article::class,
        'rex_article_action' => Content\ArticleAction::class,
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
        'rex_module_cache' => Content\ModuleCache::class,
        'rex_module_perm' => Content\ModulePermission::class,
        'rex_structure_context' => Content\StructureContext::class,
        'rex_structure_element' => Content\StructureElement::class,
        'rex_structure_perm' => Content\StructurePermission::class,
        'rex_template' => Content\Template::class,
        'rex_template_cache' => Content\TemplateCache::class,
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
        'rex_user_role_interface' => Security\UserRoleInterface::class,
        'rex_user_session' => Security\UserSession::class,
        'rex_webauthn' => Security\WebAuthn::class,
        'rex_setup' => Setup\Setup::class,
        'rex_setup_importer' => Setup\Importer::class,
        'rex_list' => View\DataList::class,
        'rex_fragment' => View\Fragment::class,
        'rex_navigation' => View\Navigation::class,
        'rex_system_report' => SystemReport::class,
        'rex_view' => View\View::class,
        'rex_be_style' => Backend\Style::class,
        'rex_scss_compiler' => Util\ScssCompiler::class,
        'rex_article_not_found_exception' => Content\Exception\ArticleNotFoundException::class,
        'rex_exception' => Exception\Exception::class,
        'rex_functional_exception' => Exception\UserMessageException::class,
        'rex_http_exception' => Http\Exception\HttpException::class,
        'rex_media_manager_not_found_exception' => MediaManager\Exception\MediaNotFoundException::class,
        'rex_socket_exception' => HttpClient\Exception\HttpClientException::class,
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
        new MethodCallRename(Addon\Addon::class, 'getAvailablePackages', 'getAvailableAddons'),
        new MethodCallRename(Addon\Addon::class, 'getSetupPackages', 'getSetupAddons'),
        new MethodCallRename(Addon\Addon::class, 'getSystemPackages', 'getSystemAddons'),
        new MethodCallRename(Console\Command\AbstractCommand::class, 'getPackage', 'getAddon'),
        new MethodCallRename(Console\Command\AbstractCommand::class, 'setPackage', 'setAddon'),

        new MethodCallRename(Security\PasswordPolicy::class, 'getRule', 'getDescription'),

        new MethodCallRename(Content\ArticleContentBase::class, 'getClang', 'getClangId'),
        new MethodCallRename(Content\ArticleSlice::class, 'getClang', 'getClangId'),
        new MethodCallRename(Content\StructureElement::class, 'getClang', 'getClangId'),

        new MethodCallRename(MediaManager\ManagedMedia::class, 'getImageWidth', 'getWidth'),
        new MethodCallRename(MediaManager\ManagedMedia::class, 'getImageHeight', 'getHeight'),

        new MethodCallRename(Mailer\Mailer::class, 'setLog', 'setArchive'),

        new MethodCallRename(Form\AbstractForm::class, 'addLinklistField', 'addArticleField'),
        new MethodCallRename(Form\AbstractForm::class, 'addLinkmapField', 'addArticleField'),
        new MethodCallRename(Form\AbstractForm::class, 'addMedialistField', 'addMediaField'),

        new MethodCallRename(Cronjob\CronjobManager::class, 'getManager', 'getExecutor'),
        new MethodCallRename(Cronjob\CronjobManager::class, 'hasManager', 'hasExecutor'),
    ])
    ->withConfiguredRule(RenameStaticMethodRector::class, [
        new RenameStaticMethod(Core::class, 'getVersionHash', Util\Version::class, 'gitHash'),
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
        new NewToStaticCall(Security\BackendPasswordPolicy::class, Security\BackendPasswordPolicy::class, 'factory'),
        new NewToStaticCall(Log\LogFile::class, Log\LogFile::class, 'factory'),
        new NewToStaticCall('rex_exception', Exception\RuntimeException::class, 'create'), // 2 step modification, see StaticCallToNewRector
    ])
    ->withConfiguredRule(StaticCallToNewRector::class, [
        new StaticCallToNew(Exception\RuntimeException::class, 'create'), // 2 step modification, see NewToStaticCallRector
    ])
    ->withConfiguredRule(FuncCallToStaticCallRector::class, [
        new FuncCallToStaticCall('rex_mediapool_filename', MediaPool\MediaPool::class, 'filename'),
        new FuncCallToStaticCall('rex_mediapool_mediaIsInUse', MediaPool\MediaPool::class, 'mediaIsInUse'),
        new FuncCallToStaticCall('rex_mediapool_isAllowedMediaType', MediaPool\MediaPool::class, 'isAllowedExtension'),
        new FuncCallToStaticCall('rex_mediapool_isAllowedMimeType', MediaPool\MediaPool::class, 'isAllowedMimeType'),
        new FuncCallToStaticCall('rex_mediapool_getMediaTypeWhitelist', MediaPool\MediaPool::class, 'getAllowedExtensions'),
        new FuncCallToStaticCall('rex_mediapool_getMediaTypeBlacklist', MediaPool\MediaPool::class, 'getBlockedExtensions'),
        new FuncCallToStaticCall('rex_mediapool_Mediaform', View\View::class, 'mediaPoolMediaForm'),
        new FuncCallToStaticCall('rex_mediapool_Uploadform', View\View::class, 'mediaPoolMediaForm'),
        new FuncCallToStaticCall('rex_mediapool_Syncform', View\View::class, 'mediaPoolMediaForm'),

        // additional adjustments necessary afterward, see https://github.com/redaxo/redaxo/pull/5918/files
        new FuncCallToStaticCall('rex_mediapool_saveMedia', MediaPool\MediaPool::class, 'addMedia'), // different params
        new FuncCallToStaticCall('rex_mediapool_updateMedia', MediaPool\MediaPool::class, 'updateMedia'), // different params
        new FuncCallToStaticCall('rex_mediapool_syncFile', MediaPool\MediaPool::class, 'addMedia'), // different params
        new FuncCallToStaticCall('rex_mediapool_deleteMedia', MediaPool\MediaPool::class, 'deleteMedia'), // different return value

        new FuncCallToStaticCall('rex_metainfo_add_field', MetaInfo\MetaInfo::class, 'addField'),
        new FuncCallToStaticCall('rex_metainfo_add_field_type', MetaInfo\MetaInfo::class, 'addFieldType'),
        new FuncCallToStaticCall('rex_metainfo_cleanup', MetaInfo\MetaInfo::class, 'cleanup'),
        new FuncCallToStaticCall('rex_metainfo_delete_field', MetaInfo\MetaInfo::class, 'deleteField'),
        new FuncCallToStaticCall('rex_metainfo_delete_field_type', MetaInfo\MetaInfo::class, 'deleteFieldType'),
        new FuncCallToStaticCall('rex_metainfo_extensions_handler', MetaInfo\MetaInfo::class, 'extensionHandler'),
        new FuncCallToStaticCall('rex_metainfo_meta_prefix', MetaInfo\MetaInfo::class, 'metaPrefix'),
        new FuncCallToStaticCall('rex_metainfo_meta_table', MetaInfo\MetaInfo::class, 'metaTable'),

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
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'PACKAGE_CACHE_DELETED', 'ADDON_CACHE_DELETED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_ADDED', 'SLICE_ADDED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_UPDATED', 'SLICE_UPDATED'),
        new ReplaceArgumentDefaultValue(ExtensionPoint\Extension::class, 'register', 0, 'STRUCTURE_CONTENT_SLICE_DELETED', 'SLICE_DELETED'),

        new ReplaceArgumentDefaultValue(Util\Markdown::class, 'parse', 1, false, $options = [
            new Expr\ArrayItem(new Expr\ConstFetch(new Name('false')), new Expr\ClassConstFetch(new Name(Util\Markdown::class), 'SOFT_LINE_BREAKS')),
        ]),
        new ReplaceArgumentDefaultValue(Util\Markdown::class, 'parseWithToc', 3, false, $options),
    ])
    ->withConfiguredRule(ConstFetchToClassConstFetchRector::class, [
        new ConstFetchToClassConstFetch('REX_FORM_ERROR_VIOLATE_UNIQUE_KEY', Form\Form::class, 'ERROR_VIOLATE_UNIQUE_KEY'),

        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_TEXT', MetaInfo\Database\Table::class, 'FIELD_TEXT'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_TEXTAREA', MetaInfo\Database\Table::class, 'FIELD_TEXTAREA'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_SELECT', MetaInfo\Database\Table::class, 'FIELD_SELECT'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_RADIO', MetaInfo\Database\Table::class, 'FIELD_RADIO'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_CHECKBOX', MetaInfo\Database\Table::class, 'FIELD_CHECKBOX'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_REX_MEDIA_WIDGET', MetaInfo\Database\Table::class, 'FIELD_REX_MEDIA_WIDGET'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_REX_MEDIALIST_WIDGET', MetaInfo\Database\Table::class, 'FIELD_REX_MEDIA_WIDGET'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_REX_LINK_WIDGET', MetaInfo\Database\Table::class, 'FIELD_REX_LINK_WIDGET'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_REX_LINKLIST_WIDGET', MetaInfo\Database\Table::class, 'FIELD_REX_LINK_WIDGET'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_DATE', MetaInfo\Database\Table::class, 'FIELD_DATE'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_DATETIME', MetaInfo\Database\Table::class, 'FIELD_DATETIME'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_LEGEND', MetaInfo\Database\Table::class, 'FIELD_LEGEND'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_TIME', MetaInfo\Database\Table::class, 'FIELD_TIME'),
        new ConstFetchToClassConstFetch('REX_METAINFO_FIELD_COUNT', MetaInfo\Database\Table::class, 'FIELD_COUNT'),
    ])
    ->withConfiguredRule(RenameClassConstFetchRector::class, [
        new RenameClassConstFetch(MetaInfo\Database\Table::class, 'FIELD_REX_MEDIALIST_WIDGET', 'FIELD_REX_MEDIA_WIDGET'),
        new RenameClassConstFetch(MetaInfo\Database\Table::class, 'FIELD_REX_LINKLIST_WIDGET', 'FIELD_REX_LINK_WIDGET'),
        new RenameClassConstFetch(MetaInfo\Form\DefaultType::class, 'REX_MEDIALIST_WIDGET', 'REX_MEDIA_WIDGET'),
        new RenameClassConstFetch(MetaInfo\Form\DefaultType::class, 'REX_LINKLIST_WIDGET', 'REX_LINK_WIDGET'),
        new RenameClassAndConstFetch(View\View::class, 'JS_ASYNC', View\Asset::class, 'JS_ASYNC'),
        new RenameClassAndConstFetch(View\View::class, 'JS_DEFERED', View\Asset::class, 'JS_DEFERED'),
        new RenameClassAndConstFetch(View\View::class, 'JS_IMMUTABLE', View\Asset::class, 'JS_IMMUTABLE'),
    ])
;
