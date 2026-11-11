<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Attach the original product photos (exported from the old WordPress media
 * library, wp-content/uploads) to the seeded products. Matching is by the
 * original file names recorded in database/seeders/data/catalog.json.
 */
class ImportProductImages extends Command
{
    protected $signature = 'store:import-images {directory : Folder containing the exported image files (searched recursively)} {--replace : Remove existing photos first}';

    protected $description = 'Attach product photos exported from the previous website';

    public function handle(): int
    {
        $dir = rtrim($this->argument('directory'), '/');
        if (! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");

            return self::FAILURE;
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
                $files[strtolower($file->getFilename())] = $file->getPathname();
            }
        }

        $catalog = json_decode(file_get_contents(database_path('seeders/data/catalog.json')), true);
        $attached = 0;

        foreach ($catalog['products'] as $entry) {
            $product = Product::query()->where('slug', $entry['slug'])->first();
            if (! $product) {
                continue;
            }
            if ($this->option('replace')) {
                Storage::disk('public')->delete($product->images()->pluck('path')->all());
                $product->images()->delete();
            }

            foreach ($entry['images'] as $i => $url) {
                $name = strtolower(basename(parse_url($url, PHP_URL_PATH)));
                $source = $files[$name] ?? null;
                if (! $source) {
                    $this->warn("{$product->name}: missing {$name}");

                    continue;
                }
                if (! @getimagesize($source)) {
                    $this->warn("{$product->name}: {$name} is not a valid image");

                    continue;
                }
                $target = "products/{$product->id}/".basename($source);
                if ($product->images()->where('path', $target)->exists()) {
                    continue;
                }
                Storage::disk('public')->put($target, file_get_contents($source));
                $product->images()->create(['path' => $target, 'alt' => $product->name, 'sort_order' => $i]);
                $attached++;
            }
        }

        $this->info("Attached {$attached} images.");

        return self::SUCCESS;
    }
}
