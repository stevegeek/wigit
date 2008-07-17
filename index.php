<?php 
  require_once('classTextile.php');
  require_once('config.php');

  function getAuthor($user) {
    global $AUTHORS;

    if (isset($AUTHORS[$user])) {
      return $AUTHORS[$user];
    }
    else if ($user != "") {
      return "$user <$user@wiggit>";
    }
    return $DEFAULT_AUTHOR;
  }

	function getHTTPUser() {
		// This code is copied from phpMyID. Thanks to the phpMyID dev(s).
		if (function_exists('apache_request_headers') && ini_get('safe_mode') == false) {
			$arh = apache_request_headers();
			$hdr = $arh['Authorization'];
		} elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
			$hdr = $_SERVER['PHP_AUTH_DIGEST'];
		} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
			$hdr = $_SERVER['HTTP_AUTHORIZATION'];
		} elseif (isset($_ENV['PHP_AUTH_DIGEST'])) {
			$hdr = $_ENV['PHP_AUTH_DIGEST'];
		} elseif (isset($_REQUEST['auth'])) {
			$hdr = stripslashes(urldecode($_REQUEST['auth']));
		} else {
			$hdr = null;
		}
		$digest = substr($hdr,0,7) == 'Digest '
			?  substr($hdr, strpos($hdr, ' ') + 1)
			: $hdr;
		if (!is_null($digest)) {
			$hdr = array();
			preg_match_all('/(\w+)=(?:"([^"]+)"|([^\s,]+))/', $digest, $mtx, PREG_SET_ORDER);
			foreach ($mtx as $m) {
				if ($m[1] == "username") {
					return $m[2] ? $m[2] : str_replace("\\\"", "", $m[3]);
				}
			}
		}
		return "";
	}

	function git($command) {
		global $GIT, $DATA_DIR;

		$gitDir = dirname(__FILE__) . "/$DATA_DIR/.git";
		$gitWorkTree = dirname(__FILE__) . "/$DATA_DIR";
		$gitCommand = "$GIT --git-dir=$gitDir --work-tree=$gitWorkTree $command";
		$output = array();
		$result;
		exec($gitCommand, $output, $result);
    // FIXME: The -1 is a hack to avoid 'commit' on an unchanged repo to
    // fail.
		if ($result != 0 && $result != 1) {
      print "Error running " . $gitCommand;
      print "Error code: " . $result;
		}
		return 1;
	}

  function filterPageName($page) {
    // FIXME
    return $page;
  }

  function parseResource($resource) {
		global $DEFAULT_PAGE;

    $matches = array();
    $page = "";
    $type = "";
    if (ereg("/(.*)/(.*)", $resource, $matches)) {
      $page = filterPageName($matches[1]);
      $type = $matches[2];
    }
    else if (ereg("/(.*)", $resource, $matches)) {
      $page = filterPageName($matches[1]);
    }
    if ($page == "") {
      $page = $DEFAULT_PAGE;
    }
    if ($type == "") {
      $type = "view";
    }
    return array("page" => $page, "type" => $type);
  }

	// Get the page
	$resource = parseResource($_GET['r']);

	// Get the HTTP user.
	$user = getHTTPUser();

	// Some common variables
	$wikiTitle = $TITLE;
	$wikiFile = $DATA_DIR . "/" . $resource["page"];
	$wikiFile = $wikiFile;
	$wikiPage = $resource["page"];
	$wikiPageViewURL = "$SCRIPT_URL/$wikiPage";
	$wikiPageEditURL = "$SCRIPT_URL/$wikiPage/edit";
	$wikiCSS = $CSS;
	$wikiHome = "$SCRIPT_URL/";
	$wikiUser = $user;

	if (isset($_POST['data'])) {
		if (trim($_POST['data']) == "") {
			// Delete
			if (file_exists($wikiFile)) {
			  if (!git("rm $wikiPage")) { return; }

        $commitMessage = "Deleted $wikiPage";
        $author = getAuthor($user);
        if (!git("commit --message='$commitMessage' --author='$author'")) { return; }
      }
      header("Location: $wikiHome");
      return;
		}
		else {
      $handle = fopen($wikiFile, "w");
			fputs($handle, stripslashes($_POST['data']));
			fclose($handle);

			$commitMessage = "Changed $wikiPage";
			$author = getAuthor($user);
			if (!git("init")) { return; }
			if (!git("add $wikiPage")) { return; }
			if (!git("commit --message='$commitMessage' --author='$author'")) { return; }
      header("Location: $wikiPageViewURL");
			return;
		}
	}
  // Get operation
	else {
    // Viewing
    if ($resource["type"] == "view") {
      if (!file_exists($wikiFile)) {
        header("Location: " . $SCRIPT_URL . "/" . $resource["page"] . "/edit");
        return;
      }

      // Open the file
      $handle = fopen($wikiFile, "r");
      $data = fread($handle, filesize($wikiFile));
			fclose($handle);

      // Add wiki links
      $wikiLinkedData = $data;

      // Textilify
      $textile = new Textile();
      $formattedData = $textile->TextileThis($wikiLinkedData);

      // Put in template
			$wikiContent = $formattedData;
			include('templates/view.php');
    }
    // Editing
    else if ($resource["type"] == "edit") {
			if (file_exists($wikiFile)) {
				$handle = fopen($wikiFile, "r");
				$data = fread($handle, filesize($wikiFile));
			}

			// Put in template
			$wikiData = $data;
			$wikiPagePostURL = "$SCRIPT_URL/$wikiPage";
			include('templates/edit.php');
    }
    // Error
    else {
      print "Unknown type: " . $resource["type"];
    }
  }

?>