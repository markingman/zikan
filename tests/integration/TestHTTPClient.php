<?php

namespace Zikan;

use RuntimeException;
use tidy;

class TestHTTPClient
{
	public static function tidy(string $html, bool $diagnose = true): string
	{
		$tidy = new tidy();

		if (!$tidy->parseString($html, [
			'drop-empty-elements' => false,
			'indent-spaces' => 4,
			'indent-with-tabs' => true,
			'indent' => true,
			'output-html' => true,
			'tab-size' => 4,
			'wrap' => 2048,
		], 'utf8')) {
			throw new RuntimeException('Failed parsing HTML');
		}

		$output = tidy_get_output($tidy);

		if (!$diagnose) {
			return $output;
		} else {
			$tidy->diagnose();

			return
				$output . PHP_EOL .
				'<!--' . PHP_EOL . trim($tidy->errorBuffer ?? '') . PHP_EOL . '-->';
		}
	}

	/** @return array{status: int, body: string}  */
	public function get(string $url/*, array $options = []*/, bool $tidy = true): array
	{
		return $this->request('GET', $url/*, $options*/, $tidy);
	}

	/** @return array{status: int, body: string}  */
	public function post(string $url/*, array $options = []*/, bool $tidy = true): array
	{
		return $this->request('POST', $url/*, $options*/, $tidy);
	}

	/** @return array{status: int, body: string}  */
	public function request(string $method, string $url/*, array $options = []*/, bool $tidy = true): array
	{
		$opts = [
			CURLOPT_URL => 'http://localhost/' . ltrim($url, '/'),
			CURLOPT_RETURNTRANSFER => true,
		];

		if ($method === 'POST') {
			$opts[CURLOPT_POST] = true;
		}

		$ch = curl_init();
		curl_setopt_array($ch, $opts);

		$response = strval(curl_exec($ch));
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($tidy) {
			$response = $this->tidy($response);
		}

		return [
			'status' => $httpcode,
			'body' => $response,
		];
	}
}
