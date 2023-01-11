<?php
/**
 * PLUGIN NAME: DUO tester
 * DESCRIPTION: test DUO Two-Factor Authentication (2FA)
 * VERSION: 1.0.0
 * AUTHOR: Francesco Delacqua
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Duo\DuoUniversal\Client;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\Duo;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\DuoStore;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;


// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";

// OPTIONAL: Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().

class DuoTester {

	/**
	 *
	 * @var REDCapConfigDTO
	 */
	private $config;

	/**
	 *
	 * @var DuoStore
	 */
	private $store;

	/**
	 *
	 * @var Duo
	 */
	private $duo;

	public function __construct()
	{
		$this->config = REDCapConfigDTO::fromDB();
		$this->store = $this->makeStore();
		$this->duo = $this->makeDuo();
		$this->username = defined('USERID') ? USERID : 'undefined';
	}

	function testFalse() {
		return false;
	}

	private function makeStore() {
		$sessionID = Duo::makeSession(USERID, @$_SERVER['HTTP_REFERER']);
		return DuoStore::fromState($sessionID);
	}

	private function makeDuo() {
		$config = $this->config;
		$two_factor_auth_duo_ikey = $config->two_factor_auth_duo_ikey;
		$two_factor_auth_duo_skey = $config->two_factor_auth_duo_skey;
		$two_factor_auth_duo_hostname = $config->two_factor_auth_duo_hostname;
		return new Duo($two_factor_auth_duo_ikey, $two_factor_auth_duo_skey, $two_factor_auth_duo_hostname);
	}

	private function maskString($secret, $showStart=0, $showEnd=0) {
		$start = $end = '';
		$totalSymbols = strlen($secret)-$showStart-$showEnd;
		if($showStart>0) $start = substr($secret, 0, $showStart);
		if($showEnd>0) $end = substr($secret, -$showEnd, $showEnd);
		$masked = $start . str_repeat('•', $totalSymbols) . $end;
		return $masked;
	}

	private function printError($th) {
		return sprintf('%s (%s)', $th->getMessage(), $th->getCode());
	}

	private function extractJWT($promptURI) {
		try {
			$urlQuery = parse_url($promptURI, PHP_URL_QUERY);
			parse_str($urlQuery, $searchParams);
			$JWT = @$searchParams['request'];
			return $JWT;
		} catch (Throwable $th) {
			return $this->printError($th);
		}
	}

	private function decodeJWT($JWT, $secret) {
		try {
			$key = new Key($secret, Client::SIG_ALGORITHM);
			return JWT::decode($JWT, $key);
		} catch (\Throwable $th) {
			return $this->printError($th);
		}
	}

	public function run() {
		$config = $this->config;
		
		$duoIntegrationKey = $config->two_factor_auth_duo_ikey;
		$duoSecretKey = $config->two_factor_auth_duo_skey;
		$client = $this->duo->getClient();
		$state = $this->store->state();
		$username = $this->username;
		$promptURI = $client->createAuthUrl($username, $state);
		$JWT = $this->extractJWT($promptURI);
		$JWT_payload = $this->decodeJWT($JWT, $duoSecretKey);

		$data = [
			'duo integration key' => $this->maskString($duoIntegrationKey, 3, 3),
			'duo secret key' => $this->maskString($duoSecretKey, 3, 3),
			'duo hostname' => $config->two_factor_auth_duo_hostname,
			'username' => $username,
			'state' => $state,
			'prompt URI' => $promptURI,
			'JWT payload' => $JWT_payload,
		];
		return $data;
	}
}

// OPTIONAL: Display the header
$HtmlPage = new HtmlPage();
$HtmlPage->PrintHeaderExt();

$isAdmin = (defined('SUPER_USER') && SUPER_USER) ?? false;
if(!$isAdmin) {
	$message = 'This page is reserved to REDCap administrators';
}

if($isAdmin) {
	try {
		$tester = new DuoTester();
		$results = $tester->run();
	} catch (\Throwable $th) {
		$message = $th->getMessage();
	}
}

// Your HTML page content goes here
?>
<style>
[data-form] {
	display: grid;
	gap: 1em;
	/* max-width: 500px; */
	margin: 0 auto;
}
main {
	max-width: 780px;
	margin: 0 auto;
}

</style>
<main>
	<h3 style="color:#800000;">DUO Configuration Check</h3>


	<?php // $isAdmin = false; ?>

	<?php if(!$isAdmin): ?>
		<div class="alert alert-danger mb-4">
			<p>This plugin is available only to REDCap administrator</p>
		</div>
	<?php else: ?>

		<?if($results): ?>
		<div class="mt-2 card p-2">
			<div class="card-body">
				<!-- <h5 class="card-title">Info</h5> -->
				<ul>
				<?php foreach ($results as $key => $value) : ?>
					<li>
						<span class="key mr-2 font-weight-bold"><?= $key ?>:</span>
						<?php
						$transformedValue = '';
						switch ($key) {
							case 'prompt URI':
								$transformedValue = $value;
								$transformedValue .= sprintf('<a class="btn btn-sm btn-primary ml-2 mt-2" href="%s" target="_blank">%s</a>', $value, 'Test...');
								break;
							case 'JWT payload':
								$transformedValue = sprintf('<pre>%s</pre>', print_r($value, true));
								break;
							default:
								$transformedValue = $value;
								break;
						}
						?>
						<span class="value"><?= $transformedValue ?></span>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endif; ?>
	<?php endif; ?>
	<?if($message): ?>
	<pre class="mt-2">
		<?= print_r($message) ?>
	</pre>
	<?php endif; ?>
</main>
<?php




// OPTIONAL: Display the footer
$HtmlPage->PrintFooterExt();