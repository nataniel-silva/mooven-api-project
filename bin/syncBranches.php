#!/usr/bin/env php
<?php
function validateBranch(string $branch) {
	if (
		!in_array($branch, ['desenv', 'homolog', 'master', 'hotfix'])
		&& !preg_match('/UG-[\d]+/', $branch)
	) {
		throw new Exception('Invalid branch name "'.$branch.'"');
	}
}

function mergeBranches(string $destBranch, array $fromBranches, $repository = 'front', $doPush = false) {
	if (!in_array($repository, ['front', 'back'])) {
		throw new Exception('Invalid repository do merge "'.$repository.'"');
	}
	
	$dir = __DIR__.'/../../goflux-'.$repository.'/';
	if (!chdir($dir)) {
		throw new Exception('Error while changing to directory "'.$dir.'"');
	}
	
	$gitFetch = 'git fetch origin';
	$gitCheckout = 'git checkout %s';
	$gitPull = 'git pull';
	$gitMerge = 'git merge origin/%s';
	$gitPush = 'git push origin %s';
	
	$cmdRet = null;
	// Sincroniza repositório local com origin
	passthru($gitFetch, $cmdRet);
	if ($cmdRet !== 0) {
		throw new Exception('Error while fetching origin ('.$repository.')');
	}
	// Checkout e pull na branch de destino
	passthru(sprintf($gitCheckout, $destBranch), $cmdRet);
	if ($cmdRet !== 0) {
		throw new Exception('Error while checking out branch "'.$destBranch.'" ('.$repository.')');
	}
	passthru($gitPull, $cmdRet);
	if ($cmdRet !== 0) {
		throw new Exception('Error while pulling out branch "'.$destBranch.'" ('.$repository.')');
	}
	// Merge das branches
	foreach ($fromBranches as $branch) {
		passthru(sprintf($gitMerge, $branch), $cmdRet);
		if ($cmdRet !== 0) {
			throw new Exception('Error while merging branch origin/"'.$branch.'" into "'.$destBranch.'" ('.$repository.')');
		}
	}
	// Push
	if ($doPush) {
		passthru(sprintf($gitPush, $destBranch), $cmdRet);
		if ($cmdRet !== 0) {
			throw new Exception('Error while pushing branch "'.$destBranch.'" ('.$repository.')');
		}
	}
}

function getSyncBranchesSynopsis() {
	return "You must provide the branches to sync.\n\n"
		."Synopsis: syncBranches.php [--only-(back|front)] [--push] branchDest branchFrom1 branchFrom2 ...\n";
}

try {
	if (count($argv) < 3) {
		throw new Exception(getSyncBranchesSynopsis());
	}
	
	$parameters = ['--only-back', '--only-front', '--push'];
	$doSyncBack = $doSyncFront = true;
	$doPush = false;
	$destBranch = '';
	$fromBranches = [];
	foreach ($argv as $ind => $branch) {
		if ($ind === 0) { // Nome do argumento
			continue;
		}
		if (in_array($branch, $parameters)) {
			switch ($branch) {
				case '--only-back':
					$doSyncFront = false;
					break;
				case '--only-front':
					$doSyncBack = false;
					break;
				case '--push':
					$doPush = true;
					break;
			}
			continue;
		}
		validateBranch($branch);
		if ($ind === 1) {
			$destBranch = $branch;
		} else {
			$fromBranches[] = $branch;
		}
	}
	
	if (empty($destBranch) || empty($fromBranches)) {
		throw new Exception(getSyncBranchesSynopsis());
	}
	
	if ($doSyncFront) {
		echo "Syncing front...\n";
		mergeBranches($destBranch, $fromBranches, 'front', $doPush);
	}
	if ($doSyncBack) {
		echo "Syncing back...\n";
		mergeBranches($destBranch, $fromBranches, 'back', $doPush);
	}
} catch (Exception $e) {
	echo $e->getMessage()."\n";
}
/*
git checkout homolog
git pull origin homolog
git merge --commit --no-edit desenv
git merge --abort
*/