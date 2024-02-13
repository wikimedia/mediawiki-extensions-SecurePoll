<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Crypt\OpenSslCrypt;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Crypt\OpenSslCrypt
 */
class OpenSslCryptTest extends MediaWikiIntegrationTestCase {
	/** @var Context */
	private $context;

	/** @var Election */
	private $election;

	protected function setUp(): void {
		// give detailed error reports in test failures
		$this->overrideConfigValue( 'SecurePollShowErrorDetail', true );

		$this->context = $this->createStub( Context::class );
		$this->election = $this->createStub( Election::class );
	}

	private function getCryptInstance( string $encryptKeyType, string $signKeyType, bool $canDecrypt ) {
		$this->election->method( 'getProperty' )
			->willReturnMap( [
				[ 'openssl-encrypt-key', false, self::KEYS['encrypt'][$encryptKeyType] ],
				[ 'openssl-sign-key', false, self::KEYS['sign'][$signKeyType] ],
				[ 'openssl-decrypt-key', false, $canDecrypt ? self::KEYS['decrypt'][$encryptKeyType] : false ]
			] );

		$this->election->method( 'getId' )->willReturn( 1 );

		return new OpenSslCrypt( $this->context, $this->election );
	}

	public function testRoundTripTraditional() {
		$crypt = $this->getCryptInstance( 'valid1', 'valid', true );
		$record = 'vote_data';

		$encrypted = $crypt->encrypt( $record );
		$this->assertStatusOK( $encrypted, 'Failed to encrypt' );

		$decrypted = $crypt->decrypt( $encrypted->getValue() );
		$this->assertStatusOK( $decrypted, 'Failed to decrypt' );

		$this->assertEquals( $record, $decrypted->getValue() );
	}

	public function testRoundTripPkcs8() {
		$crypt = $this->getCryptInstance( 'valid2', 'valid', true );
		$record = 'vote_data';

		$encrypted = $crypt->encrypt( $record );
		$this->assertStatusOK( $encrypted, 'Failed to encrypt' );

		$decrypted = $crypt->decrypt( $encrypted->getValue() );
		$this->assertStatusOK( $decrypted, 'Failed to decrypt' );

		$this->assertEquals( $record, $decrypted->getValue() );
	}

	public function testCanDecryptTrue() {
		$crypt = $this->getCryptInstance( 'valid1', 'valid', true );
		$this->assertTrue( $crypt->canDecrypt() );
	}

	public function testCanDecryptFalse() {
		$crypt = $this->getCryptInstance( 'valid1', 'valid', false );
		$this->assertFalse( $crypt->canDecrypt() );
	}

	public function testWeakRsaKeyEncrypt() {
		$crypt = $this->getCryptInstance( 'weak', 'valid', false );
		$status = $crypt->encrypt( 'vote_data' );
		$this->assertStatusError( 'securepoll-full-openssl-error', $status );
	}

	public function testWeakRsaKeySign() {
		$crypt = $this->getCryptInstance( 'valid1', 'weak', false );
		$status = $crypt->encrypt( 'vote_data' );
		$this->assertStatusError( 'securepoll-full-openssl-error', $status );
	}

	public function testUnsupportedKeyEncrypt() {
		$crypt = $this->getCryptInstance( 'invalid1', 'valid', false );
		$status = $crypt->encrypt( 'vote_data' );
		$this->assertStatusError( 'securepoll-full-openssl-error', $status );
	}

	public function testUnsupportedKeySignEC() {
		$crypt = $this->getCryptInstance( 'valid1', 'invalid1', false );
		$status = $crypt->encrypt( 'vote_data' );
		$this->assertStatusError( 'securepoll-full-openssl-error', $status );
	}

	public function testUnsupportedKeySignEdDSA() {
		$crypt = $this->getCryptInstance( 'valid1', 'invalid2', false );
		$status = $crypt->encrypt( 'vote_data' );
		$this->assertStatusError( 'securepoll-full-openssl-error', $status );
	}

	public function testGarbageKeyEncrypt() {
		$crypt = $this->getCryptInstance( 'invalid2', 'valid', false );
		$status = $crypt->encrypt( 'vote_data' );
		$this->assertStatusError( 'securepoll-full-openssl-error', $status );
	}

	private const KEYS = [
		'encrypt' => [
			// RSA 1024
			'weak' => '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDO1AshVI9UHA/cCeXTLerQ1Cqx
Nj5eyRfEas246Ooms4k2iOPLyawGXQ8ydXekuhsLK4MbFkvseH9TQdnHLs3tf0Ed
gHfsZER7dInPDYt0ktWJL1B0A6mQZdYHN4QVNUSM9xQHG7n2mjKKFgPieu2bLeg+
AELhIRonMGGG/9p24QIDAQAB
-----END PUBLIC KEY-----',
			// RSA 2048
			'valid1' => '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA/auLhiS9v9K9iIipHvXG
JiCIVEtxSd+m21XkF/KNAqqS4OnLc+oQghJs6H84KMmUF5r/70fXDwEt6ZUfTMBv
BM3bnTnfS9UuCht7FoxlLeZgPPzoRHRKGP1K6pSCHO/OC3H+6ZiUjqQ0ozfXhK/5
ER6RCAVikotPgr/RUXZcTqfhfkseiOlLsgw5bLFZZsdP2PPjrAGjUjAlyZYUBclE
ClaT9bp+xlZ++U2p18U8AbRzINUjP/qJm0Zhy3OOJsR7TIMK7lO75NYDTDmmlwvB
Fy9ZLCn1vN1iDiZ8/b1Zkmim//XdFSHZ7RyVU3qh9ZTq7/jDstAEMzqSzJYoRbgz
5wIDAQAB
-----END PUBLIC KEY-----',
			// RSA 2048
			'valid2' => '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA6k8sq3s35KgVC5f1U1eV
wH/ZRRNEJb1qB/He1GQmpcawIOGRAknN1HIH+eyV418nitfme2Nqfa7t9EJKrdAj
1ZdDZm/NkVYqTOZQzjGOt0p2O4kGn8uHzwonvTfUjeRVYD4Z/uBEy//G88FRBa4A
0M/vHdFPPn/XwX8S+BxQpfMCMKfwKzL70GNRPdQ8XrdapOsJVWK1IGxbJO1NwVBl
Lbxnsh+uLoKNMUVMc7W7QxphVXCJee+5Fc1fUqaouHiaXkLE+a6odgrwMBJREOry
Dfag3Z7+SI91koxzI2yMkiS3EfwK9KEZJY7oOEmzPA/rI11NZ1IxYL0HaHcM4LEY
uwIDAQAB
-----END PUBLIC KEY-----',
			// NIST P-256
			'invalid1' => '-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEEPnGBec+E+Ac1nmKBWo7rf3cRM2p
eO9JoKwocyxA6RT0l7+HY3ikTDnmx1d5E1G70+9bQnMGeCu8Y0QOmb6xuA==
-----END PUBLIC KEY-----',
			// garbage
			'invalid2' => '-----BEGIN PRIVATE KEY-----
lol
-----END PRIVATE KEY-----'
		],
		'decrypt' => [
			// RSA 1024
			'weak' => '-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDO1AshVI9UHA/cCeXTLerQ1CqxNj5eyRfEas246Ooms4k2iOPL
yawGXQ8ydXekuhsLK4MbFkvseH9TQdnHLs3tf0EdgHfsZER7dInPDYt0ktWJL1B0
A6mQZdYHN4QVNUSM9xQHG7n2mjKKFgPieu2bLeg+AELhIRonMGGG/9p24QIDAQAB
AoGBAIW3+/YxmHT1q59EWu1cu3sGIQ/vlQVtF8uqjZTmKTSaULUMbdJ8cZavpbqm
zZKXrBJdsGbb1HxvL0qhwW4QqZugYEmKs0kvGI3vQ8I5/CEQCnFPbJY0SNElWyJq
6Qn4GtPjSJyygLIdf1nYp1b/ZZHEqjEd1k7d8fnh+lUXDKRBAkEA6UtHgKKkvV9/
Lvd0vqaymnfzxuW7QzfXFv7ZUZLzL7b3P2PFgTA1QvYfW0PSHvW9glecLf+xCmRH
39BwuQwAeQJBAOL1WNeVTy2D0ea3Waayqg4xGGDVv+TdTw38RY1GVgd7TVwErGGD
OGHtYx9svhtaYjQDx4QxYl4br1SZ9kz7n6kCQD7JWdcO7tNgrjQPuUXOS2czPVKc
ZYezsZmowQgpSnXfAi/3RCJ+vPbmko3XdOeML5KyzX+7vwQS9R0RViUXS9ECQQDi
tKEh94s3s80f4VY0JZOoyXHIiLs+KbKmDbCMBg45OvlzSVfHsAPKDg5NfZoISKh/
vKa3bX0M9CqXH5PgIhjBAkB3nVv6l6pKFkUQdGjTMdBLOwMGVTAp7YFxxAplHDQb
Amv0bWiOv4l4YqdL0y4IGlcD0lIIb5L86oVD5XiuqImn
-----END RSA PRIVATE KEY-----',
			// RSA 2048, openssl "traditional" format
			'valid1' => '-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA/auLhiS9v9K9iIipHvXGJiCIVEtxSd+m21XkF/KNAqqS4OnL
c+oQghJs6H84KMmUF5r/70fXDwEt6ZUfTMBvBM3bnTnfS9UuCht7FoxlLeZgPPzo
RHRKGP1K6pSCHO/OC3H+6ZiUjqQ0ozfXhK/5ER6RCAVikotPgr/RUXZcTqfhfkse
iOlLsgw5bLFZZsdP2PPjrAGjUjAlyZYUBclEClaT9bp+xlZ++U2p18U8AbRzINUj
P/qJm0Zhy3OOJsR7TIMK7lO75NYDTDmmlwvBFy9ZLCn1vN1iDiZ8/b1Zkmim//Xd
FSHZ7RyVU3qh9ZTq7/jDstAEMzqSzJYoRbgz5wIDAQABAoIBAEDPW05ihLhFt3by
s4LdeEq1dR9URxWAtrjDMQuNRMmxmuUr1nc/DgitfHXKSybgmZ2OVGVmDWQgAQL0
vpJ1d+faUInTB2HwjVq3urMAG7mmamAqnY0fyO6piEJ2vud1vUbfUSmNeicXfPtW
gRG9g/0Dujo6Zfza6u2U5H3Tp7HQYGy01aR4Z7URk6adTLgE9a3VUpWZOHvGCU1T
s7BQta3ZcFV6aqGuUsmYCCaq579wEawLNnzXk5C8GnIzrOyjwkMImt4AYbOkbz4n
kz9kIg2HUyT0AI+wDFSXCLPTwU68tEe23Jl67pmKXSNKwFA9BBf7+xsschX4/Qjb
az36XcECgYEA/bP7FFW6LnjaoItXYEZAdUFbZ7Rmt2UYtfCuOLXIqAdNr92BZ3gZ
6vE/uLQzPmTSSeiQT8KfjkCUQ5WqCfpFdw6ALAoMUBtdVpw4e9RpY/1ZvJClJOi6
nCYO0s9T3zGqvVkVJXl8cXEooTgleKB179Dx6YchLvaW2/J6rDpd+80CgYEA//d8
5IH7rdXbO8wtRzQSM/W6+FB32OQvdHGY8xoeHg1SjQZSxmT43wKhLfK4ixewHkVn
ZuAXVjpJvKFza/N89Dzd15MDisr3to4kXyA2dkWZ6bikaQ0fxrEfaW8rYxm1bVCZ
dyTjTAoQt5kSUt9/TAP+nCvP5jOKmB+S69ELwoMCgYBbQx4jv37c7r5+mAFTcZII
W/1cfcmhWS2dbTnOzp2izBXHGKBPLyXrnOL2N2RjonL/ATjAaFtrGZH8Cd+D/VJ1
tHKo5Ohu/JkD6ehrqwpKXL2mFkZB07IlL4+Ph+M826n2uQ/hTiVUzYb3D7JzBQ3H
udr7zY9DTJMSBdTZFZnvVQKBgG7f4TWKKF6wV/qbArHpGElxk7+j+4Xzsaqa2knT
cmBKHQwBRTGy4yqWlQK2NMHrTNqP7a5eOz2amf4fYUPNgL667s1GXS5tpMdq4s5h
wbWRiI348R6gFLeYWabuBLk0laX4V8GINPznDz64C3viW92tjUhQoglxIgOrCZUk
RBfJAoGBAJ4HoHBeUP9sRC0WaPa8vXeFvw928x2/qISFeANjFBSXaQa0FyQuN6/p
UnWn3goyhsgUueEyqvhKRTorlatfJdIVfMI2JX9dw3ziC2qItr9v1341mE+cEQNf
mF0bLqXzqZmNuGz8aI1AKTtq5WCcxEvlznKqBtxDUr8wVrTYbEWh
-----END RSA PRIVATE KEY-----',
			// RSA 2048, PKCS#8 format
			'valid2' => '-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDqTyyrezfkqBUL
l/VTV5XAf9lFE0QlvWoH8d7UZCalxrAg4ZECSc3Ucgf57JXjXyeK1+Z7Y2p9ru30
Qkqt0CPVl0Nmb82RVipM5lDOMY63SnY7iQafy4fPCie9N9SN5FVgPhn+4ETL/8bz
wVEFrgDQz+8d0U8+f9fBfxL4HFCl8wIwp/ArMvvQY1E91Dxet1qk6wlVYrUgbFsk
7U3BUGUtvGeyH64ugo0xRUxztbtDGmFVcIl577kVzV9Spqi4eJpeQsT5rqh2CvAw
ElEQ6vIN9qDdnv5Ij3WSjHMjbIySJLcR/Ar0oRkljug4SbM8D+sjXU1nUjFgvQdo
dwzgsRi7AgMBAAECggEADfJqiUaLXCv5HLiN3Mkg29WAXFE6RDjTH+fwyJmxhzqd
b0ed9xHnk5a6DNFdVqxqUSRqzrtrMlIATgLIKTJzf5pCY82R4e3MFMm805k1kol3
IjIU19womFVVajjxiQSnZFQt/fLhhXcTI51FMl5aJBPMeuzjl6fKcvUabvRYhJgm
R/H++QAIyI64VKWeO2Werx08Lo0A3oU5tZp/5IqobMDK3a9pOvvd/BVl715d7dsR
H/IURSbjojBGopcACcIg+y/3U3fDbQ6hsh1APy6k46o/tknkMjKI8YFUKn3WNiCX
vu4AXYtQY24GLhZ363Blkc5Zg/jQ8IMnXJlzzlaYyQKBgQDzhKlCPzm+Bod+vIDi
XocZJCFRNVNhJOAHLu00Bw98IZIbwEPQZGkqgCpJ9PzjYs+LFLjxy2zlxEqAanZo
OjTG/opfaMHrAAxrB0amJ490ewOpx8j+2MHdpN3s08y/BbAH7nHy3OlUTuKgrw0r
1hFZq/AKMt5oromkBvVi3FER9wKBgQD2Ua2B9C0ZQzr8Fjrygqi8kN8HFc9yudok
qDjVNpNFWpJm0cAc5N4QNJddyyeIIM0aLEtt/sJMUwbJdOkjxAMz87TRwH8WyYew
4bpif+gNiIF555jN4ndaof/J+zs4c+zZsdRLs/13WmtWS09tnTqt7yFyChpYEDvt
eGvqeUp+XQKBgCFuM+JRVbb9pHpEnJj3CyrrZ9uDK5gpfMvfqFa9JWYQIcPHiEfC
kSFxiNywUktRFFbumFkxaRi98tBgTTMP0pEC/rIMapq9XfcPf6ZtxebiwGT9dV1v
GmY4LPNHPFN5D2p3VJNXunyFe909StyavbUGwqRTyW6rj1/CSMuynTqPAoGAZKq8
3n+MbuarFJuBO9tqWqZaf+rJ/Xk3TgPyNjE3vwBlFOBkQ2rF1pmnww1j+p5tNGUl
7q5PeiH0hCDjp1pUdMcE/zRMvGNgsUmPgvIDRISlZczhBkOnw8KxZzrl2QyHn/Ct
csL8s+qgM4RT0diQQsWYRaMbYSJBW6v7brhjWYECgYAesbFm+HQd0VadxVESWdQA
rBtEzvC4+WWx1KxIk5vzoPpGVE9g7xR3to1eKJK5b0yDPX7XEkGPzb2yMDWkLgFR
CX9gXG1T7WlXs/Csyw4jLjhAXM55B5uSALhUNPpmXJgGzvI6WfFCEUuFR4zR8kMz
igbVvHHvv0ULu7Ujktu1gg==
-----END PRIVATE KEY-----',
			// NIST P-256 (currently not supported)
			'invalid1' => '-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgOwUSbvxRGadudlGI
86yRK4Ozabpvu8l1EkUclZB9KLqhRANCAAQQ+cYF5z4T4BzWeYoFajut/dxEzal4
70mgrChzLEDpFPSXv4djeKRMOebHV3kTUbvT71tCcwZ4K7xjRA6ZvrG4
-----END PRIVATE KEY-----',
		],
		'sign' => [
			// RSA 1024
			'weak' => '-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQCTlurWZJNUOzQrBmaHBrS3ryPL/CSkeRaFJIfQ6UmDG8ilcbJS
7JvF0bzLJyMVxVTQVEQN84BWqP2s0nu6KZlqC7mxEBWUBlznCOgPPrGy0GK/y+wA
oCMm/GQIat7fdjE7dxcq9lanqkLg+cdyf9wsQwSZNFYf05DyzFjVuecYhQIDAQAB
AoGActIUfRrd68frfBWMqjuzWLvu04GkWDjEJm0XJJZ51IBHBz9/BjiBr9LOWBZo
m0VcCH+mOlxYWhM617sg+uHmMYXdR+DDoOtFi/Ttc2Hssgs13c18OtQrLsJgOg0z
B0GfvZKy/bQr2MlXeAtR9pFNT9YayYo2TKmkaMIBXDvSxQECQQDD0zYZ42tVF6dM
Oev6QAAXpXEISg3fTW45Zue10lgBo4gJS3UB1x0bRDlcRbfKhR/qHggdDLsxGvqd
uhM8y58hAkEAwPE02TJ7XQ5iLbnGXLcWnA9gKP8BcVKMf7ZwrzL3rlwysEfjRMan
V8rxEX3S1Ta/XlH3qilQjQvDeD9PQEnA5QJBAIWTbTP7jRteJyA34l+xiF6Y26Fr
4kqP5sABEc1UzfNQOVsdvZKjyZbv0rC+XcAnTr3D/oTSSl4UqR8t7jFqNAECQF/A
VMXlc6t+0+J1a6ffhOz2BHbT9tdSbD+FPu7zkE+Lmew6AOPE4o+DO5e+pb8CaH5a
l1pvHnag++TVJnN1JrECQQCdOS2tCBmZ+1S14jY+YLuMUR1CHxTXo3mZagPx4Ey+
nKF4blj8n1jgsH6Zl5CP+1hLXRvfaYwX6rhv1+U/htMS
-----END RSA PRIVATE KEY-----',
			// RSA 2048
			'valid' => '-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA32RI21uYZVbtwx1mQthCeCUTRAw4hS2Ze2E20zDsL6Qw0AV+
ZC2iVQoNCJ7e55Y1194C2dN+ga0K0+igVfE5PBJ3kugRUiiOIvD8XJpMGbGXEFBb
1tngB/J6FF9r/aRLDjmDXkknki8uMZtNCDJYkYvoAzCEEnzRRqilA83Z+ZxjRPoP
KkTlq/CJ8JSTHGzWaqrutj/QOc0gEO9nT33aaEiGmpSMx5wxuODXP6NV84xofNTN
PUAW0TneYa9Wece0BnHciqz/RRPUG52HZ1D5c1Uv3KQ29bacgEvUTagN8d7V22Ju
BU6czVMSL9y4EYTRB4TaHK/1zQScpq4+bm19GQIDAQABAoIBABv3ME5oYLihsZwm
dwx73ukELdhBS2YTYk5uyhqVhPZ7MIfU2Dtu9w58S/YTUBu1KJfAjvd5TDIBrq7+
mpaHtJQ6SkaAJNhLLsKzC8oFSuzrLYUjpOD4X4e/KYdEDCncQm/NGrlX9nXiJX9H
o39uMLuabyN5vwmYHz7K5/WE9KUUXIY7PLdbtnHR6sGxQ90j8eihpi5m5YBPukEu
zqyMurl83bhti8/ZKKbd6nw145JY5oVEdR8Yhyv32NHEDd3MKrzhYEBiLZPMIdQL
6uEbtoxgWjp6NRTsevIVXr1FDVwPpafmDvgOWoICW/Rce0PTMBZPAI4a0EYL9YMz
uAPYPy0CgYEA/3cHNa6pQ21uoPBonMvVc37iVt56k9+Dlu86nVOgpX1lzJ2cRkXc
HFRTFT6ojPtIGAi40/bzWON2CQ+7G74PeuIL4DMuHp8NM/Z1mLBHaTXklpaoiujO
6AnEVKWydMog4BQq5pv39uBm1rFQ2dcc7zbId0d9ycSXAuaaFHwlfX8CgYEA39wP
UZiNDBwcRjoIJ6pTqfZ1WWzAhvE1iaTkZhzVRcf7dCA15Nbnf4jF7DUJD+Bhvisl
mnPAscPRLzUTCc0ZQiCOjlEojOaAGaePtKU2XGNgGsrCWFbJZoVkk9234T24GkzG
VsMsHHmpuBYYovbLpruJLP5sqnYmyJIUCuxlgWcCgYEA3a1Gx1FxtX3vQp562JyT
823GMEVMLqcEw+OBhGW9//ypan3VzrDuSHLvyX6euf+O1rQy1lPzZE207jYFUhSB
0Er4rSscORwhU9bown8s6GIIopfHVwqgCH/5Ks7B5VBEaM13ULdE26hr9J7XHsDo
bbenCsIxp/T8aVr7lw0HZzcCgYArJCe3m+SiztAMIKbtrf+No7ed0j2QQ/ADZMm3
fBsF5bJqAMMiJOBcTamHCfmDG8T6dyo6B9C88XXg2SOgIeyaN9gBSVa9QLazOv2Q
3L4LNAOTZjvp9l+96Dsn2/TR7akLOHyIv/hCTJgOqAN7dIBu03luOGIMv9RdlvlR
2Zj+RQKBgCebkM2uNnATifPVc11rFZgJAnRziCaAgGGbik9ihqzEObDhDuVJef8U
jFVPaDRJnDOwRtDNsFvcjetrAfogzPW0sC0oa08lql2w8CayYX7KqyYjS5TdYQN5
7YjyV5bGKSmHv2rqThYVwSBDWyEve+ldwveLfMQo1PjJFWSic8x5
-----END RSA PRIVATE KEY-----',
			// NIST P-256 (currently not supported)
			'invalid1' => '-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQg+iQqK2Uc4e1lvnwf
BJ/3J8C7VvH+68PIlCt7MKRkGXOhRANCAARH+awqVebo+3lRUFNcJRLe+GDge8/k
ih/yan1amDw0izc4Srm3sytVtyTUQDeAXe4+dmR/T2oL4BoHqx4veyeG
-----END PRIVATE KEY-----',
			// Ed25519 (impossible to support using PHP openssl)
			'invalid2' => '-----BEGIN PRIVATE KEY-----
MC4CAQAwBQYDK2VwBCIEIBvCJnGuAsRSfDgNJseg+IgnRpOgmAhA4FQ11o3SVX9s
-----END PRIVATE KEY-----',
		]
	];
}
