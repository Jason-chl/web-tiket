-- Create event_schedule table for band lineup schedules
CREATE TABLE IF NOT EXISTS `event_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_event` int(11) NOT NULL,
  `nama_band` varchar(255) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_event` (`id_event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add jam_selesai to event table if not exists
ALTER TABLE `event` 
ADD COLUMN IF NOT EXISTS `jam_mulai` time NULL AFTER `tanggal`,
ADD COLUMN IF NOT EXISTS `jam_selesai` time NULL AFTER `jam_mulai`;
