<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ReportRoutesSelector;

abstract class BaseReportCommand extends Command
{
    /**
     * El nombre único de este proceso de reporte (usado en el helper).
     */
    abstract protected function getProcessName(): string;

    /**
     * Retorna la tabla de rutas activa para el comando.
     */
    protected function getRoutesTable(): string
    {
        return ReportRoutesSelector::getTableForProcess($this->getProcessName());
    }

    /**
     * Retorna el disco de SFTP configurado redireccionado si aplica.
     */
    protected function getSftpDisk(string $defaultDisk): string
    {
        return ReportRoutesSelector::getSftpDiskForProcess($this->getProcessName(), $defaultDisk);
    }
}
