#!/usr/bin/env php
<?php

function ask($question): string
{
    echo $question . "\n> ";
    return trim(fgets(STDIN));
}

if (file_exists('composer.json')) {
    echo "You have already installed. Delete all files except init.php from the folder and try again.\n";
    exit;
}

$packageName = ask("Enter the package name (format: package-name):");
$packageDescription = ask("Enter the package description:");
$authorLogin = ask("Enter the author login (format: username):");
$authorName = ask("Enter the author's full name:");
$authorEmail = ask("Enter the author's email:");
$createConfig = in_array(strtolower(ask("Do you want to create a config file? (Y/n):")), ['y', '']);
$createMigration = in_array(strtolower(ask("Do you want to create a migration file? (Y/n):")), ['y', '']);
$createRoutes = in_array(strtolower(ask("Do you want to create a routes file? (Y/n):")), ['y', '']);
$createControllers = in_array(strtolower(ask("Do you want to create controllers? (Y/n):")), ['y', '']);
$createLanguageFiles = in_array(strtolower(ask("Do you want to create language files? (Y/n):")), ['y', '']);
$createViews = in_array(strtolower(ask("Do you want to create views? (Y/n):")), ['y', '']);

try {
    createReadme($packageName, $authorLogin);

    createComposerJson($packageName, $packageDescription, $authorLogin, $authorName, $authorEmail);

    createProvider(
        $packageName,
        $authorLogin,
        $createConfig,
        $createMigration,
        $createRoutes,
        $createLanguageFiles,
        $createViews,
    );

    if ($createConfig) {
        createConfig();
    }

    if ($createMigration) {
        createMigration();
    }

    if ($createRoutes) {
        createRoutes($packageName, $authorLogin);
    }

    if ($createControllers) {
        createControllers($packageName, $authorLogin);
    }

    if ($createLanguageFiles) {
        createLanguageFiles();
    }

    if ($createViews) {
        createViews();
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

function createReadme(string $packageName, string $authorLogin): void
{
    $readmeContent = "# $packageName\n\n" .
        "## Installation\n\n" .
        "You can install this package via composer:\n\n" .
        "```bash\n" .
        "composer require $authorLogin/$packageName\n" .
        "```";

    file_put_contents("README.md", $readmeContent);
}

/**
 * @throws JsonException
 */
function createComposerJson(string $packageName, string $packageDescription, string $authorLogin, string $authorName, string $authorEmail): void
{
    $author = str_replace(' ', '', ucwords(str_replace('-', ' ', $authorLogin)));
    $package = str_replace(' ', '', ucwords(str_replace('-', ' ', $packageName)));

    $composerJson = [
        "name" => "$authorLogin/$packageName",
        "description" => $packageDescription,
        "autoload" => [
            "psr-4" => [
                "$author\\$package\\" => "src",
            ]
        ],
        "extra" => [
            "laravel" => [
                "providers" => [
                    "$author\\$package\\ServiceProvider"
                ]
            ]
        ],
        "type" => "library",
        "license" => "MIT",
        "authors" => [
            [
                "name" => $authorName,
                "email" => $authorEmail
            ]
        ],
        "require" => [
            "php" => ">=8.3"
        ],
        "require-dev" => [
            "orchestra/testbench" => "^10.0"
        ]
    ];

    file_put_contents('composer.json', json_encode($composerJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function createProvider(
    string $packageName,
    string $authorLogin,
    bool$createConfig = false,
    bool $createMigration = false,
    bool $createRoutes = false,
    bool $createLanguageFiles = false,
    bool $createViews = false,
): void {
    $author = str_replace(' ', '', ucwords(str_replace('-', ' ', $authorLogin)));
    $package = $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $packageName)));

    if (! is_dir("src") && ! mkdir("src", 0777, true) && ! is_dir("src")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "src"));
    }

    $providerContent = "<?php\n\n" .
        "namespace $author\\$package;\n\n" .
        "use Illuminate\\Support\\ServiceProvider;\n\n" .
        "class {$className}ServiceProvider extends ServiceProvider\n" .
        "{\n" .
        "    public function register()\n" .
        "    {\n";

    if ($createConfig) {
        $providerContent .= "        \$this->mergeConfigFrom(\n" .
            "            __DIR__.'/../config/$packageName.php', '$packageName'\n" .
            "        );\n\n";
    }

    $providerContent .= "    public function boot()\n" .
    "    {\n";

    if ($createConfig) {
        $providerContent .= "        \$this->publishes([\n" .
            "            __DIR__.'/../config/$packageName.php' => config_path('$packageName.php'),\n" .
            "        ], '$packageName-config');\n\n";
    }

    if ($createMigration) {
        $providerContent .= "        \$this->publishesMigrations([\n" .
            "            __DIR__.'/../database/migrations' => database_path('migrations'),\n" .
            "        ], '$packageName-migrations');\n\n";
    }

    if ($createRoutes) {
        $providerContent .= "        \$this->loadRoutesFrom(__DIR__.'/../routes/web.php');\n\n";
    }

    if ($createLanguageFiles) {
        $providerContent .= "        \$this->loadTranslationsFrom(__DIR__.'/../lang', '$packageName');\n\n";

        $providerContent .= "        \$this->publishes([\n" .
            "            __DIR__.'/../lang' => \$this->app->langPath('vendor/$packageName'),\n" .
            "        ], '$packageName-lang');\n\n";
    }

    if ($createViews) {
        $providerContent .= "        \$this->loadViewsFrom(__DIR__.'/../resources/views', '$packageName');\n\n";

        $providerContent .= "        \$this->publishes([\n" .
            "            __DIR__.'/../resources/views' => resource_path('views/vendor/$packageName'),\n" .
            "        ], '$packageName-views');\n\n";
    }

    file_put_contents("src/{$className}ServiceProvider.php", $providerContent);
}

function createConfig(): void
{
    if (! is_dir("config") && ! mkdir("config", 0777, true) && ! is_dir("config")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "config"));
    }

    $configFileName = "config/config.php";
    $configContent = "<?php\n\nreturn [\n\n];\n";

    file_put_contents($configFileName, $configContent);
}

function createMigration(): void
{
    if (! is_dir("database/migrations") && ! mkdir("database/migrations", 0777, true) && ! is_dir("database/migrations")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "database/migrations"));
    }

    $datePrefix = date('Y_m_d') . "_000000";
    $migrationFileName = "database/migrations/{$datePrefix}_XXX.php";
    $migrationContent = "<?php\n\n" .
        "use Illuminate\\Database\\Migrations\\Migration;\n" .
        "use Illuminate\\Database\\Schema\\Blueprint;\n" .
        "use Illuminate\\Support\\Facades\\Schema;\n\n" .
        "return new class extends Migration\n" .
        "{\n" .
        "    public function up(): void\n" .
        "    {\n" .
        "        Schema::table('XXX', static function (Blueprint \$table) {\n\n" .
        "        });\n" .
        "    }\n\n" .
        "    public function down(): void\n" .
        "    {\n" .
        "        Schema::table('XXX', static function (Blueprint \$table) {\n\n" .
        "        });\n" .
        "    }\n" .
        "};";
    file_put_contents($migrationFileName, $migrationContent);
}

function createRoutes(string $packageName, string $authorLogin): void
{
    $author = str_replace(' ', '', ucwords(str_replace('-', ' ', $authorLogin)));
    $package = $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $packageName)));

    if (! is_dir("routes") && ! mkdir("routes", 0777, true) && ! is_dir("routes")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "routes"));
    }

    $routesContent = "<?php\n\n" .
        "use $author\\$package\\Http\\Controllers\\{$className}Controller;\n\n" .
        "Route::middleware(['auth', 'web'])->group(function () {\n\n" .
        "});\n";

    file_put_contents("routes/web.php", $routesContent);
}

function createControllers(string $packageName, string $authorLogin): void
{
    $author = str_replace(' ', '', ucwords(str_replace('-', ' ', $authorLogin)));
    $package = $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $packageName)));

    if (! is_dir("src/Http/Controllers") && ! mkdir("src/Http/Controllers", 0777, true) && ! is_dir("src/Http/Controllers")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "src/Http/Controllers"));
    }

    $controllerContent = "<?php\n\n" .
        "namespace $author\\$package\\Http\\Controllers;\n\n" .
        "use Illuminate\\Foundation\\Auth\\Access\\AuthorizesRequests;\n" .
        "use Illuminate\\Foundation\\Validation\\ValidatesRequests;\n" .
        "use Illuminate\\Routing\\Controller as BaseController;\n\n" .
        "class Controller extends BaseController\n" .
        "{\n" .
        "    use AuthorizesRequests, ValidatesRequests;\n" .
        "}";

    file_put_contents("src/Http/Controllers/Controller.php", $controllerContent);

    $controllerContent = "<?php\n\n" .
        "namespace $author\\$package\\Http\\Controllers;\n\n" .
        "use Illuminate\\View\\View;\n" .
        "use Illuminate\\Http\\RedirectResponse;\n\n" .
        "class $className extends Controller\n" .
        "{\n" .
        "    public function __invoke(): View\n" .
        "    {\n\n" .
        "    }\n" .
        "}";

    file_put_contents("src/Http/Controllers/$className.php", $controllerContent);
}

function createLanguageFiles(): void
{
    if (! is_dir("lang/en") && ! mkdir("lang/en", 0777, true) && ! is_dir("lang/en")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "lang"));
    }

    $commonLangFileName = "lang/en/common.php";
    $commonLangContent = "<?php\n\nreturn [\n\n];\n";

    file_put_contents($commonLangFileName, $commonLangContent);

    $specificLangFileName = "lang/en/specific.php";
    $specificLangContent = "<?php\n\nreturn [\n\n];\n";

    file_put_contents($specificLangFileName, $specificLangContent);
}

function createViews(): void
{
    if (! is_dir("resources/views") && ! mkdir("resources/views", 0777, true) && ! is_dir("resources/views")) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', "resources/views"));
    }

    $viewFile = "resources/views/xxx.blade.php";
    $viewContent = "<div>\n\n</div>\n";

    file_put_contents($viewFile, $viewContent);
}

echo "Laravel package created successfully.\n";
