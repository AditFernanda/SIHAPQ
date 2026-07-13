<?php

use Illuminate\Support\Facades\Schedule;

// Backup harian jam 04:00 (jam istirahat pabrik) — aktivitas input QC paling
// sepi dan tepat sebelum hari operasional baru mulai (07:00), sehingga backup
// bersih dan menangkap hampir seluruh data hari itu.
Schedule::command('qc:backup-database')->dailyAt('04:00');
