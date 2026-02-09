<?php

namespace App\Console\Commands;

use Doctrine\Inflector\InflectorFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateModule extends Command
{
    protected $signature = 'module:create {name}';

    protected $description = 'Create a new module';

    public function handle(): void
    {
        $moduleName = $this->argument('name');
        $modulePath = base_path("modules/{$moduleName}");

        if (File::exists($modulePath)) {
            $this->error('Module already exists!');
            return;
        }

        File::makeDirectory($modulePath, 0755, true);

        $this->generateFolders($moduleName, $modulePath);
        $this->generateFiles($moduleName, $modulePath);

        $this->info("Module {$moduleName} created successfully!");
    }

    /**
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function generateFolders(string $moduleName, string $modulePath): void
    {
        $folders = [
            'Actions',
            'Database/Factories',
            'Database/Migrations',
            'DataTransferObjects',
            'Http/Controllers',
            'Http/Requests',
            'Http/Resources',
            'Models',
            'Providers',
            'routes',
            'Tests',
        ];

        foreach ($folders as $folder) {
            File::makeDirectory($modulePath . '/' . $folder, 0755, true);
        }
    }

    /**
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function generateFiles(string $moduleName, string $modulePath): void
    {
        $stubPath = base_path('stubs/module');

        File::copyDirectory($stubPath, $modulePath);

        $files = File::allFiles($modulePath);

        foreach ($files as $file) {
            $fileRelativePath = $file->getRelativePathname();
            $newFilePath = $modulePath . '/' . $fileRelativePath;

            $originalFileName = $file->getFilename();
            if (str_contains($originalFileName, 'resourceName')) {
                $newFileName = str_replace(['{{moduleName}}', '.stub'], [$moduleName, ''], '{{moduleName}}Resource.php');
            } else {
                $newFileName = str_replace(['{{moduleName}}', '.stub'], [$moduleName, ''], $originalFileName);
            }

            $newFilePathWithCorrectName = $modulePath . '/' . $file->getRelativePath() . '/' . $newFileName;

            // Renombrar el archivo con el nombre correcto
            File::move($newFilePath, $newFilePathWithCorrectName);

            $contents = file_get_contents($newFilePathWithCorrectName);
            if (is_string($contents)) {
                $contents = str_replace('{{moduleName}}', $moduleName, $contents);
                file_put_contents($newFilePathWithCorrectName, $contents);
            }
        }

        // Generar modelo, controlador y migración
        $this->generateModel($moduleName, $modulePath);
        $this->generateController($moduleName, $modulePath);
        $this->generateMigration($moduleName, $modulePath);
        $this->generateFormRequest($moduleName, $modulePath);
}

    /**
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function generateModel(string $moduleName, string $modulePath): void
    {
        $modelName = ucfirst($moduleName);

        $modelStubPath = base_path('stubs/module/Models/modelName.php.stub');
        $modelStubContent = file_get_contents($modelStubPath);
        $modelContent = str_replace('{{modelName}}', $modelName, $modelStubContent);
        $modelContent = str_replace('{{moduleName}}', $moduleName, $modelContent);

        $modelPath = $modulePath . '/Models/' . $modelName . '.php';
        file_put_contents($modelPath, $modelContent);

        // Eliminar el archivo Model.php si existe
        $extraModelPath = $modulePath . '/Models/modelName.php';
        if (file_exists($extraModelPath)) {
            unlink($extraModelPath);
        }
    }

    /**
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function generateController(string $moduleName, string $modulePath): void
    {
        $controllerName = ucfirst($moduleName) . 'Controller';

        $controllerStubPath = base_path('stubs/module/Http/Controllers/controllerName.php.stub');
        $controllerStubContent = file_get_contents($controllerStubPath);
        $controllerContent = str_replace('{{controllerName}}', $controllerName, $controllerStubContent);
        $controllerContent = str_replace('{{moduleName}}', $moduleName, $controllerContent);

        $controllerPath = $modulePath . '/Http/Controllers/' . $controllerName . '.php';
        file_put_contents($controllerPath, $controllerContent);

        // Eliminar el archivo controllerName.php si existe
        $extraControllerPath = $modulePath . '/Http/Controllers/controllerName.php';
        if (file_exists($extraControllerPath)) {
            unlink($extraControllerPath);
        }
    }

    /**
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function generateMigration(string $moduleName, string $modulePath): void
    {
        $singularName = Str::snake($moduleName);
        $inflector = InflectorFactory::create()->build();
        $pluralName = $inflector->pluralize($singularName);
        $migrationName = 'create_' . $pluralName . '_table';

        $migrationStubPath = base_path('stubs/module/Database/Migrations/migration.php.stub');
        $migrationStubContent = file_get_contents($migrationStubPath);

        $timestamp = date('Y_m_d_His');
        $migrationFileName = $timestamp . '_' . $migrationName . '.php';

        $migrationContent = str_replace(
            ['{{migrationName}}', '{{table}}'],
            [$migrationName, $pluralName],
            $migrationStubContent
        );

        $migrationPath = $modulePath . '/Database/Migrations/' . $migrationFileName;
        file_put_contents($migrationPath, $migrationContent);

        // Eliminar el archivo migration.php si existe
        $extraMigrationPath = $modulePath . '/Database/Migrations/migration.php';
        if (file_exists($extraMigrationPath)) {
            unlink($extraMigrationPath);
        }
    }

    /**
     * @param string $moduleName
     * @param string $modulePath
     * @return void
     */
    protected function generateFormRequest(string $moduleName, string $modulePath): void
    {
        $requestName = ucfirst($moduleName) . 'Request';

        $requestStubPath = base_path('stubs/module/Http/Requests/Request.php.stub');
        $requestStubContent = file_get_contents($requestStubPath);
        $requestContent = str_replace(['{{moduleName}}', '{{requestName}}'], [$moduleName, $requestName], $requestStubContent);

        $requestPath = $modulePath . '/Http/Requests/' . $requestName . '.php';
        file_put_contents($requestPath, $requestContent);

        // Eliminar el archivo Request.php si existe
        $requestFilePath = $modulePath . '/Http/Requests/Request.php';
        if (file_exists($requestFilePath)) {
            unlink($requestFilePath);
        }
    }
}
