<?php

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace ChristophWurst\KItinerary\Bin;

use ChristophWurst\KItinerary\Adapter;
use KItineraryRuntimeException;
use function fclose;
use function fwrite;
use function in_array;
use function is_array;
use function is_resource;
use function json_decode;
use function php_uname;
use function proc_close;
use function proc_open;
use function stream_get_contents;

class BinaryAdapter implements Adapter
{

	private static $isAvailable = null;

	private function canRunBinary(): bool {
		if (in_array('proc_open', explode(',', ini_get('disable_functions')), true)) {
			return false;
		}
		if (php_uname('m') !== 'x86_64') {
			// The binary only support x86_64
			return false;
		}

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w']
		];

		$proc = proc_open(__DIR__ . '/../bin/kitinerary-extractor', $descriptors, $pipes);
		if (!is_resource($proc)) {
			return false;
		}
		fclose($pipes[0]);
		fclose($pipes[1]);
		$ret = proc_close($proc);

		return $ret === 0;
	}

	public function isAvailable(): bool
	{
		if (self::$isAvailable === null) {
			self::$isAvailable = $this->canRunBinary();
		}
		return self::$isAvailable;
	}

	public function extractFromString(string $source): array
	{
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w']
		];

		$proc = proc_open(__DIR__ . '/../bin/kitinerary-extractor', $descriptors, $pipes);
		if (!is_resource($proc)) {
			throw new KItineraryRuntimeException("Could not invoke shipped kitinerary-extractor");
		}
		fwrite($pipes[0], $source);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		if ($output === false) {
			throw new KItineraryRuntimeException('Could not get kitinerary-extractor output');
		}
		fclose($pipes[1]);

		$ret = proc_close($proc);
		if ($ret !== 0) {
			throw new KItineraryRuntimeException("kitinerary-extractor returned exit code $ret");
		}

		$decoded = json_decode($output, true);
		if (!is_array($decoded)) {
			return [];
		}
		return $decoded;
	}

}
