<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
    public function handle(\App\Support\SppBillingService $billingService)
    {
        $id = $this->option('id');
        
        $query = \App\Models\DataSantri::query()
            ->where('is_deleted', false)
            ->whereRaw('UPPER(status) = ?', ['AKTIF']);

        if ($id) {
            $query->where('id_santri', $id);
        }

        $count = $query->count();
        $this->info("Found {$count} active santri to provision.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunk(100, function ($santriList) use ($billingService, $bar) {
            foreach ($santriList as $santri) {
                $billingService->provisionBillingForActiveSantri($santri);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Provisioning completed.');
    }
}
