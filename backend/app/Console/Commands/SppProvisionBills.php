<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DataSantri;
use App\Support\SppBillingService;

class SppProvisionBills extends Command
{
    protected $signature = 'spp:provision-bills {--id= : Santri ID to provision}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision SPP bills for active santri based on settings';

    /**
     * Execute the console command.
     */
    public function handle(SppBillingService $billingService): int
    {
        $id = $this->option('id');
        
        $query = DataSantri::query()
            ->where('is_deleted', false)
            ->whereRaw('UPPER(status) = ?', ['AKTIF']);

        if ($id) {
            $query->where('id_santri', $id);
            $this->info("Provisioning SPP for specific santri ID: {$id}");
        } else {
            $this->info("Provisioning SPP for all active santri...");
        }

        $count = $query->count();
        $this->info("Found {$count} active santri to process.");

        if ($count === 0) {
            $this->warn("No active santri found. Exiting.");
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        $query->chunk(100, function ($santriList) use ($billingService, $bar, &$successCount, &$errorCount) {
            foreach ($santriList as $santri) {
                try {
                    $billingService->provisionBillingForActiveSantri($santri);
                    $successCount++;
                } catch (\Exception $e) {
                    $this->error("\nError provisioning santri {$santri->id_santri}: " . $e->getMessage());
                    $errorCount++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        
        $this->info("Provisioning completed!");
        $this->info("  ✓ Successfully processed: {$successCount} santri");
        if ($errorCount > 0) {
            $this->error("  ✗ Errors encountered: {$errorCount} santri");
        }

        return 0;
    }
}
