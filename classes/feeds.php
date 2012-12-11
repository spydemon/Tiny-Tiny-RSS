<?php
require_once "colors.php";

class Feeds extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index");

		return array_search($method, $csrf_ignored) !== false;
	}

	private function make_gradient($end, $class) {
		$start = $class == "even" ? "#f0f0f0" : "#ffffff";

		return "style='background: linear-gradient(left , $start 6%, $end 100%);
			background: -o-linear-gradient(left , $start 6%, $end 100%);
			background: -moz-linear-gradient(left , $start 6%, $end 100%);
			background: -webkit-linear-gradient(left , $start 6%, $end 100%);
			background: -ms-linear-gradient(left , $start 6%, $end 100%);
			background: -webkit-gradient(linear, left top, right top,
				color-stop(0.06, $start), color-stop(1, $end));'";
	}

	private function format_headline_subtoolbar($feed_site_url, $feed_title,
			$feed_id, $is_cat, $search, $match_on,
			$search_mode, $view_mode, $error) {

		$page_prev_link = "viewFeedGoPage(-1)";
		$page_next_link = "viewFeedGoPage(1)";
		$page_first_link = "viewFeedGoPage(0)";

		$catchup_page_link = "catchupPage()";
		$catchup_feed_link = "catchupCurrentFeed()";
		$catchup_sel_link = "catchupSelection()";

		$archive_sel_link = "archiveSelection()";
		$delete_sel_link = "deleteSelection()";

		$sel_all_link = "selectArticles('all')";
		$sel_unread_link = "selectArticles('unread')";
		$sel_none_link = "selectArticles('none')";
		$sel_inv_link = "selectArticles('invert')";

		$tog_unread_link = "selectionToggleUnread()";
		$tog_marked_link = "selectionToggleMarked()";
		$tog_published_link = "selectionTogglePublished()";

		$set_score_link = "setSelectionScore()";

		if ($is_cat) $cat_q = "&is_cat=$is_cat";

		if ($search) {
			$search_q = "&q=$search&m=$match_on&smode=$search_mode";
		} else {
			$search_q = "";
		}

		$rss_link = htmlspecialchars(get_self_url_prefix() .
			"/public.php?op=rss&id=$feed_id$cat_q$search_q");

		// right part

		$reply .= "<span class='r'>";
		$reply .= "<span id='feed_title'>";

		if ($feed_site_url) {
			$target = "target=\"_blank\"";
			$reply .= "<a title=\"".__("Visit the website")."\" $target href=\"$feed_site_url\">".
				truncate_string($feed_title,30)."</a>";

			if ($error) {
				$reply .= " (<span class=\"error\" title=\"$error\">Error</span>)";
			}

		} else {
			$reply .= $feed_title;
		}

		$reply .= "</span>";

		$reply .= "
			<a href=\"#\"
				title=\"".__("View as RSS feed")."\"
				onclick=\"displayDlg('generatedFeed', '$feed_id:$is_cat:$rss_link')\">
				<img class=\"noborder\" style=\"vertical-align : middle\" src=\"images/feed-icon-12x12.png\"></a>";

		$reply .= "</span>";

		// left part

		$reply .= __('Select:')."
			<a href=\"#\" onclick=\"$sel_all_link\">".__('All')."</a>,
			<a href=\"#\" onclick=\"$sel_unread_link\">".__('Unread')."</a>,
			<a href=\"#\" onclick=\"$sel_inv_link\">".__('Invert')."</a>,
			<a href=\"#\" onclick=\"$sel_none_link\">".__('None')."</a></li>";

		$reply .= " ";

		$reply .= "<select dojoType=\"dijit.form.Select\"
			onchange=\"headlineActionsChange(this)\">";
		$reply .= "<option value=\"false\">".__('Actions...')."</option>";

		$reply .= "<option value=\"0\" disabled=\"1\">".__('Selection toggle:')."</option>";

		$reply .= "<option value=\"$tog_unread_link\">".__('Unread')."</option>
			<option value=\"$tog_marked_link\">".__('Starred')."</option>
			<option value=\"$tog_published_link\">".__('Published')."</option>";

		$reply .= "<option value=\"0\" disabled=\"1\">".__('Selection:')."</option>";

		$reply .= "<option value=\"$catchup_sel_link\">".__('Mark as read')."</option>";
		$reply .= "<option value=\"$set_score_link\">".__('Set score')."</option>";

		if ($feed_id != "0") {
			$reply .= "<option value=\"$archive_sel_link\">".__('Archive')."</option>";
		} else {
			$reply .= "<option value=\"$archive_sel_link\">".__('Move back')."</option>";
			$reply .= "<option value=\"$delete_sel_link\">".__('Delete')."</option>";

		}

		$reply .= "<option value=\"emailArticle(false)\">".__('Forward by email').
			"</option>";

		$reply .= "<option value=\"0\" disabled=\"1\">".__('Feed:')."</option>";

		$reply .= "<option value=\"catchupPage()\">".__('Mark as read')."</option>";

		$reply .= "<option value=\"displayDlg('generatedFeed', '$feed_id:$is_cat:$rss_link')\">".__('View as RSS')."</option>";

		$reply .= "</select>";

		//$reply .= "</div>";

		//$reply .= "</h2";

		return $reply;
	}

	private function format_headlines_list($feed, $method, $view_mode, $limit, $cat_view,
					$next_unread_feed, $offset, $vgr_last_feed = false,
					$override_order = false, $include_children = false) {

		$disable_cache = false;

		$reply = array();

		$timing_info = getmicrotime();

		$topmost_article_ids = array();

		if (!$offset) $offset = 0;
		if ($method == "undefined") $method = "";

		$method_split = explode(":", $method);

		if ($method == "ForceUpdate" && $feed > 0 && is_numeric($feed)) {
			// Update the feed if required with some basic flood control

			$result = db_query($this->link,
				"SELECT cache_images,cache_content,".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
					FROM ttrss_feeds WHERE id = '$feed'");

				if (db_num_rows($result) != 0) {
					$last_updated = strtotime(db_fetch_result($result, 0, "last_updated"));
					$cache_images = sql_bool_to_bool(db_fetch_result($result, 0, "cache_images"));
					$cache_content = sql_bool_to_bool(db_fetch_result($result, 0, "cache_content"));

					if (!$cache_images && !$cache_content && time() - $last_updated > 120 || isset($_REQUEST['DevForceUpdate'])) {
						include "rssfuncs.php";
						update_rss_feed($this->link, $feed, true, true);
					} else {
						db_query($this->link, "UPDATE ttrss_feeds SET last_updated = '1970-01-01', last_update_started = '1970-01-01'
							WHERE id = '$feed'");
					}
				}
		}

		if ($method_split[0] == "MarkAllReadGR")  {
			catchup_feed($this->link, $method_split[1], false);
		}

		// FIXME: might break tag display?

		if (is_numeric($feed) && $feed > 0 && !$cat_view) {
			$result = db_query($this->link,
				"SELECT id FROM ttrss_feeds WHERE id = '$feed' LIMIT 1");

			if (db_num_rows($result) == 0) {
				$reply['content'] = "<div align='center'>".__('Feed not found.')."</div>";
			}
		}

		if (is_numeric($feed) && $feed > 0) {

			$result = db_query($this->link, "SELECT rtl_content FROM ttrss_feeds
				WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) == 1) {
				$rtl_content = sql_bool_to_bool(db_fetch_result($result, 0, "rtl_content"));
			} else {
				$rtl_content = false;
			}

			if ($rtl_content) {
				$rtl_tag = "dir=\"RTL\"";
			} else {
				$rtl_tag = "";
			}
		} else {
			$rtl_tag = "";
			$rtl_content = false;
		}

		@$search = db_escape_string($_REQUEST["query"]);

		if ($search) {
			$disable_cache = true;
		}

		@$search_mode = db_escape_string($_REQUEST["search_mode"]);
		$match_on = "both"; // deprecated, TODO: remove

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H0", $timing_info);

//		error_log("format_headlines_list: [" . $feed . "] method [" . $method . "]");
		if( $search_mode == '' && $method != '' ){
		    $search_mode = $method;
		}
//		error_log("search_mode: " . $search_mode);
		$qfh_ret = queryFeedHeadlines($this->link, $feed, $limit, $view_mode, $cat_view,
			$search, $search_mode, $match_on, $override_order, $offset, 0,
			false, 0, $include_children);

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H1", $timing_info);

		$result = $qfh_ret[0];
		$feed_title = $qfh_ret[1];
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];
		$cache_content = true;

		$vgroup_last_feed = $vgr_last_feed;

		$reply['toolbar'] = $this->format_headline_subtoolbar($feed_site_url,
			$feed_title,
			$feed, $cat_view, $search, $match_on, $search_mode, $view_mode,
			$last_error);

		$headlines_count = db_num_rows($result);

		if (get_pref($this->link, 'COMBINED_DISPLAY_MODE')) {
			$button_plugins = array();
			foreach (explode(",", ARTICLE_BUTTON_PLUGINS) as $p) {
				$pclass = "button_" . trim($p);

				if (class_exists($pclass)) {
					$plugin = new $pclass($link);
					array_push($button_plugins, $plugin);
				}
			}
		}

		if (db_num_rows($result) > 0) {

			$lnum = $offset;

			$num_unread = 0;
			$cur_feed_title = '';

			$fresh_intl = get_pref($this->link, "FRESH_ARTICLE_MAX_AGE") * 60 * 60;

			if ($_REQUEST["debug"]) $timing_info = print_checkpoint("PS", $timing_info);

			while ($line = db_fetch_assoc($result)) {
				$class = ($lnum % 2) ? "even" : "odd";

				$id = $line["id"];
				$feed_id = $line["feed_id"];
				$label_cache = $line["label_cache"];
				$labels = false;
				$label_row_style = "";

				if ($label_cache) {
					$label_cache = json_decode($label_cache, true);

					if ($label_cache) {
						if ($label_cache["no-labels"] == 1)
							$labels = array();
						else
							$labels = $label_cache;
					}
				}

				if (!is_array($labels)) $labels = get_article_labels($this->link, $id);

				if (count($labels) > 0) {
					for ($i = 0; $i < min(4, count($labels)); $i++) {
						$bg = rgb2hsl(_color_unpack($labels[$i][3]));

						if ($bg && $bg[1] > 0) {
							$bg[1] = 0.1;
							$bg[2] = 1;

							$bg = _color_pack(hsl2rgb($bg));
							$label_row_style = $this->make_gradient($bg, $class);;

							break;
						}
					}
				}

				$labels_str = "<span id=\"HLLCTR-$id\">";
				$labels_str .= format_article_labels($labels, $id);
				$labels_str .= "</span>";

				if (count($topmost_article_ids) < 3) {
					array_push($topmost_article_ids, $id);
				}

				if ($line["last_read"] == "" && !sql_bool_to_bool($line["unread"])) {

					$update_pic = "<img id='FUPDPIC-$id' src=\"".
						theme_image($this->link, 'images/updated.png')."\"
						alt=\"Updated\">";
				} else {
					$update_pic = "<img id='FUPDPIC-$id' src=\"images/blank_icon.gif\"
						alt=\"Updated\">";
				}

				if (sql_bool_to_bool($line["unread"]) &&
					time() - strtotime($line["updated_noms"]) < $fresh_intl) {

					$update_pic = "<img id='FUPDPIC-$id' src=\"".
						theme_image($this->link, 'images/fresh_sign.png')."\" alt=\"Fresh\">";
				}

				if ($line["unread"] == "t" || $line["unread"] == "1") {
					$class .= " Unread";
					++$num_unread;
					$is_unread = true;
				} else {
					$is_unread = false;
				}

				if ($line["marked"] == "t" || $line["marked"] == "1") {
					$marked_pic = "<img id=\"FMPIC-$id\"
						src=\"".theme_image($this->link, 'images/mark_set.png')."\"
						class=\"markedPic\" alt=\"Unstar article\"
						onclick='javascript:toggleMark($id)'>";
				} else {
					$marked_pic = "<img id=\"FMPIC-$id\"
						src=\"".theme_image($this->link, 'images/mark_unset.png')."\"
						class=\"markedPic\" alt=\"Star article\"
						onclick='javascript:toggleMark($id)'>";
				}

				if ($line["published"] == "t" || $line["published"] == "1") {
					$published_pic = "<img id=\"FPPIC-$id\" src=\"".theme_image($this->link,
						'images/pub_set.png')."\"
						class=\"markedPic\"
						alt=\"Unpublish article\" onclick='javascript:togglePub($id)'>";
				} else {
					$published_pic = "<img id=\"FPPIC-$id\" src=\"".theme_image($this->link,
						'images/pub_unset.png')."\"
						class=\"markedPic\"
						alt=\"Publish article\" onclick='javascript:togglePub($id)'>";
				}

#				$content_link = "<a target=\"_blank\" href=\"".$line["link"]."\">" .
#					$line["title"] . "</a>";

#				$content_link = "<a
#					href=\"" . htmlspecialchars($line["link"]) . "\"
#					onclick=\"view($id,$feed_id);\">" .
#					$line["title"] . "</a>";

#				$content_link = "<a href=\"javascript:viewContentUrl('".$line["link"]."');\">" .
#					$line["title"] . "</a>";

				$updated_fmt = make_local_datetime($this->link, $line["updated_noms"], false);

				if (get_pref($this->link, 'SHOW_CONTENT_PREVIEW')) {
					$content_preview = truncate_string(strip_tags($line["content_preview"]),
						100);
				}

				$score = $line["score"];

				$score_pic = theme_image($this->link,
					"images/" . get_score_pic($score));

/*				$score_title = __("(Click to change)");
				$score_pic = "<img class='hlScorePic' src=\"images/$score_pic\"
					onclick=\"adjustArticleScore($id, $score)\" title=\"$score $score_title\">"; */

				$score_pic = "<img class='hlScorePic' score='$score' onclick='changeScore($id, this)' src=\"$score_pic\"
					title=\"$score\">";

				if ($score > 500) {
					$hlc_suffix = "H";
				} else if ($score < -100) {
					$hlc_suffix = "L";
				} else {
					$hlc_suffix = "";
				}

				$entry_author = $line["author"];

				if ($entry_author) {
					$entry_author = " - $entry_author";
				}

				$has_feed_icon = feed_has_icon($feed_id);

				if ($has_feed_icon) {
					$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"".ICONS_URL."/$feed_id.ico\" alt=\"\">";
				} else {
					$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"images/feed-icon-12x12.png\" alt=\"\">";
				}

				if (!get_pref($this->link, 'COMBINED_DISPLAY_MODE')) {

					if (get_pref($this->link, 'VFEED_GROUP_BY_FEED')) {
						if ($feed_id != $vgroup_last_feed && $line["feed_title"]) {

							$cur_feed_title = $line["feed_title"];
							$vgroup_last_feed = $feed_id;

							$cur_feed_title = htmlspecialchars($cur_feed_title);

							$vf_catchup_link = "(<a onclick='catchupFeedInGroup($feed_id);' href='#'>".__('mark as read')."</a>)";

							$reply['content'] .= "<div class='cdmFeedTitle'>".
								"<div style=\"float : right\">$feed_icon_img</div>".
								"<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a> $vf_catchup_link</div>";

						}
					}

					$mouseover_attrs = "onmouseover='postMouseIn($id)'
						onmouseout='postMouseOut($id)'";

					$reply['content'] .= "<div class='$class' id='RROW-$id' $label_row_style $mouseover_attrs>";

					$reply['content'] .= "<div class='hlUpdPic'>$update_pic</div>";

					$reply['content'] .= "<div class='hlLeft'>";

					$reply['content'] .= "<input type=\"checkbox\" onclick=\"tSR(this)\"
							id=\"RCHK-$id\">";

					$reply['content'] .= "$marked_pic";
					$reply['content'] .= "$published_pic";

					$reply['content'] .= "</div>";

					$reply['content'] .= "<div onclick='return hlClicked(event, $id)'
						class=\"hlTitle\"><span class='hlContent$hlc_suffix'>";
					$reply['content'] .= "<a id=\"RTITLE-$id\"
						href=\"" . htmlspecialchars($line["link"]) . "\"
						onclick=\"\">" .
						truncate_string($line["title"], 200);

					if (get_pref($this->link, 'SHOW_CONTENT_PREVIEW')) {
						if ($content_preview) {
							$reply['content'] .= "<span class=\"contentPreview\"> - $content_preview</span>";
						}
					}

					$reply['content'] .= "</a></span>";

					$reply['content'] .= $labels_str;

					if (!get_pref($this->link, 'VFEED_GROUP_BY_FEED') &&
						defined('_SHOW_FEED_TITLE_IN_VFEEDS')) {
						if (@$line["feed_title"]) {
							$reply['content'] .= "<span class=\"hlFeed\">
								(<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a>)
							</span>";
						}
					}

					$reply['content'] .= "</div>";

					$reply['content'] .= "<span class=\"hlUpdated\">$updated_fmt</span>";
					$reply['content'] .= "<div class=\"hlRight\">";

					$reply['content'] .= $score_pic;

					if ($line["feed_title"] && !get_pref($this->link, 'VFEED_GROUP_BY_FEED')) {

						$reply['content'] .= "<span onclick=\"viewfeed($feed_id)\"
							style=\"cursor : pointer\"
							title=\"".htmlspecialchars($line['feed_title'])."\">
							$feed_icon_img<span>";
					}

					$reply['content'] .= "</div>";
					$reply['content'] .= "</div>";

				} else {

					if (get_pref($this->link, 'VFEED_GROUP_BY_FEED') && $line["feed_title"]) {
						if ($feed_id != $vgroup_last_feed) {

							$cur_feed_title = $line["feed_title"];
							$vgroup_last_feed = $feed_id;

							$cur_feed_title = htmlspecialchars($cur_feed_title);

							$vf_catchup_link = "(<a onclick='javascript:catchupFeedInGroup($feed_id);' href='#'>".__('mark as read')."</a>)";

							$has_feed_icon = feed_has_icon($feed_id);

							if ($has_feed_icon) {
								$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"".ICONS_URL."/$feed_id.ico\" alt=\"\">";
							} else {
								//$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\" alt=\"\">";
							}

							$reply['content'] .= "<div class='cdmFeedTitle'>".
								"<div style=\"float : right\">$feed_icon_img</div>".
								"<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a> $vf_catchup_link</div>";
						}
					}

					$expand_cdm = get_pref($this->link, 'CDM_EXPANDED');

					$mouseover_attrs = "onmouseover='postMouseIn($id)'
						onmouseout='postMouseOut($id)'";

					$reply['content'] .= "<div class=\"$class\" $label_row_style
						id=\"RROW-$id\" $mouseover_attrs'>";

					$reply['content'] .= "<div class=\"cdmHeader\">";

					$reply['content'] .= "<div>";

					$reply['content'] .= "<input type=\"checkbox\" onclick=\"toggleSelectRowById(this,
							'RROW-$id')\" id=\"RCHK-$id\"/>";

					$reply['content'] .= "$marked_pic";
					$reply['content'] .= "$published_pic";

					$reply['content'] .= "</div>";

					$reply['content'] .= "<div id=\"PTITLE-FULL-$id\" style=\"display : none\">" .
						htmlspecialchars(strip_tags($line['title'])) . "</div>";

					$reply['content'] .= "<span id=\"RTITLE-$id\"
						onclick=\"return cdmClicked(event, $id);\"
						class=\"titleWrap$hlc_suffix\">
						<a class=\"title\"
						title=\"".htmlspecialchars($line['title'])."\"
						target=\"_blank\" href=\"".
						htmlspecialchars($line["link"])."\">".
						$line["title"] .
						" $entry_author</a>";

					$reply['content'] .= $labels_str;

					if (!get_pref($this->link, 'VFEED_GROUP_BY_FEED') &&
						defined('_SHOW_FEED_TITLE_IN_VFEEDS')) {
						if (@$line["feed_title"]) {
							$reply['content'] .= "<span class=\"hlFeed\">
								(<a href=\"#\" onclick=\"viewfeed($feed_id)\">".
								$line["feed_title"]."</a>)
							</span>";
						}
					}

					if (!$expand_cdm)
						$content_hidden = "style=\"display : none\"";
					else
						$excerpt_hidden = "style=\"display : none\"";

					$reply['content'] .= "<span $excerpt_hidden
						id=\"CEXC-$id\" class=\"cdmExcerpt\"> - $content_preview</span>";

					$reply['content'] .= "</span>";

					$reply['content'] .= "<div>";
					$reply['content'] .= "<span class='updated'>$updated_fmt</span>";
					$reply['content'] .= "$score_pic";

					if (!get_pref($this->link, "VFEED_GROUP_BY_FEED") && $line["feed_title"]) {
						$reply['content'] .= "<span style=\"cursor : pointer\"
							title=\"".htmlspecialchars($line["feed_title"])."\"
							onclick=\"viewfeed($feed_id)\">$feed_icon_img</span>";
					}
					$reply['content'] .= "<div class=\"updPic\">$update_pic</div>";
					$reply['content'] .= "</div>";

					$reply['content'] .= "</div>";

					$reply['content'] .= "<div class=\"cdmContent\" $content_hidden
						onclick=\"return cdmClicked(event, $id);\"
						id=\"CICD-$id\">";

					$reply['content'] .= "<div class=\"cdmContentInner\">";

			if ($line["orig_feed_id"]) {

				$tmp_result = db_query($this->link, "SELECT * FROM ttrss_archived_feeds
					WHERE id = ".$line["orig_feed_id"]);

						if (db_num_rows($tmp_result) != 0) {

							$reply['content'] .= "<div clear='both'>";
							$reply['content'] .= __("Originally from:");

							$reply['content'] .= "&nbsp;";

							$tmp_line = db_fetch_assoc($tmp_result);

							$reply['content'] .= "<a target='_blank'
								href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
								$tmp_line['title'] . "</a>";

							$reply['content'] .= "&nbsp;";

							$reply['content'] .= "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
							$reply['content'] .= "<img title='".__('Feed URL')."'class='tinyFeedIcon' src='images/pub_set.png'></a>";

							$reply['content'] .= "</div>";
						}
					}

					$feed_site_url = $line["site_url"];

					if ($cache_content && $line["cached_content"] != "") {
						$line["content_preview"] =& $line["cached_content"];
					}

					$article_content = sanitize($this->link, $line["content_preview"],
							false, false, $feed_site_url);

					$reply['content'] .= "<div id=\"POSTNOTE-$id\">";
					if ($line['note']) {
						$reply['content'] .= format_article_note($id, $line['note']);
					}
					$reply['content'] .= "</div>";

					$reply['content'] .= "<span id=\"CWRAP-$id\">";
					$reply['content'] .= $article_content;
					$reply['content'] .= "</span>";

/*					$tmp_result = db_query($this->link, "SELECT always_display_enclosures FROM
						ttrss_feeds WHERE id = ".
						(($line['feed_id'] == null) ? $line['orig_feed_id'] :
							$line['feed_id'])." AND owner_uid = ".$_SESSION["uid"]);

					$always_display_enclosures = sql_bool_to_bool(db_fetch_result($tmp_result,
						0, "always_display_enclosures")); */

					$always_display_enclosures = sql_bool_to_bool($line["always_display_enclosures"]);

					$reply['content'] .= format_article_enclosures($this->link, $id, $always_display_enclosures,
						$article_content);

					$reply['content'] .= "</div>";

					$reply['content'] .= "<div class=\"cdmFooter\">";

					$tag_cache = $line["tag_cache"];

					$tags_str = format_tags_string(
						get_article_tags($this->link, $id, $_SESSION["uid"], $tag_cache),
						$id);

					$reply['content'] .= "<img src='".theme_image($this->link,
							'images/tag.png')."' alt='Tags' title='Tags'>
						<span id=\"ATSTR-$id\">$tags_str</span>
						<a title=\"".__('Edit tags for this article')."\"
						href=\"#\" onclick=\"editArticleTags($id, $feed_id, true)\">(+)</a>";

					$num_comments = $line["num_comments"];
					$entry_comments = "";

					if ($num_comments > 0) {
						if ($line["comments"]) {
							$comments_url = htmlspecialchars($line["comments"]);
						} else {
							$comments_url = htmlspecialchars($line["link"]);
						}
						$entry_comments = "<a target='_blank' href=\"$comments_url\">$num_comments comments</a>";
					} else {
						if ($line["comments"] && $line["link"] != $line["comments"]) {
							$entry_comments = "<a target='_blank' href=\"".htmlspecialchars($line["comments"])."\">comments</a>";
						}
					}

					if ($entry_comments) $reply['content'] .= "&nbsp;($entry_comments)";

					$reply['content'] .= "<div style=\"float : right\">";

					$reply['content'] .= "<img src=\"images/art-zoom.png\"
						onclick=\"zoomToArticle(event, $id)\"
						style=\"cursor : pointer\"
						alt='Zoom'
						title='".__('Open article in new tab')."'>";

					//$note_escaped = htmlspecialchars($line['note'], ENT_QUOTES);

					foreach ($button_plugins as $p) {
						$reply['content'] .= $p->render($id, $line);
					}

					$reply['content'] .= "<img src=\"images/digest_checkbox.png\"
						style=\"cursor : pointer\" style=\"cursor : pointer\"
						onclick=\"dismissArticle($id)\"
						title='".__('Close article')."'>";

					$reply['content'] .= "</div>";
					$reply['content'] .= "</div>";

					$reply['content'] .= "</div>";

					$reply['content'] .= "</div>";

				}

				++$lnum;
			}

			if ($_REQUEST["debug"]) $timing_info = print_checkpoint("PE", $timing_info);

		} else {
			$message = "";

			switch ($view_mode) {
				case "unread":
					$message = __("No unread articles found to display.");
					break;
				case "updated":
					$message = __("No updated articles found to display.");
					break;
				case "marked":
					$message = __("No starred articles found to display.");
					break;
				default:
					if ($feed < -10) {
						$message = __("No articles found to display. You can assign articles to labels manually (see the Actions menu above) or use a filter.");
					} else {
						$message = __("No articles found to display.");
					}
			}

			if (!$offset && $message) {
				$reply['content'] .= "<div class='whiteBox'>$message";

				$reply['content'] .= "<p class=\"small\"><span class=\"insensitive\">";

				$result = db_query($this->link, "SELECT ".SUBSTRING_FOR_DATE."(MAX(last_updated), 1, 19) AS last_updated FROM ttrss_feeds
					WHERE owner_uid = " . $_SESSION['uid']);

				$last_updated = db_fetch_result($result, 0, "last_updated");
				$last_updated = make_local_datetime($this->link, $last_updated, false);

				$reply['content'] .= sprintf(__("Feeds last updated at %s"), $last_updated);

				$result = db_query($this->link, "SELECT COUNT(id) AS num_errors
					FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ".$_SESSION["uid"]);

				$num_errors = db_fetch_result($result, 0, "num_errors");

				if ($num_errors > 0) {
					$reply['content'] .= "<br/>";
					$reply['content'] .= "<a class=\"insensitive\" href=\"#\" onclick=\"showFeedsWithErrors()\">".
						__('Some feeds have update errors (click for details)')."</a>";
				}
				$reply['content'] .= "</span></p></div>";
			}
		}

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H2", $timing_info);

		return array($topmost_article_ids, $headlines_count, $feed, $disable_cache,
			$vgroup_last_feed, $reply);
	}

	function catchupAll() {
		db_query($this->link, "UPDATE ttrss_user_entries SET
						last_read = NOW(),unread = false WHERE owner_uid = " . $_SESSION["uid"]);
		ccache_zero_all($this->link, $_SESSION["uid"]);
	}

	function collapse() {
		$cat_id = db_escape_string($_REQUEST["cid"]);
		$mode = (int) db_escape_string($_REQUEST['mode']);
		toggle_collapse_cat($this->link, $cat_id, $mode);
	}

	function view() {
		$timing_info = getmicrotime();

		$reply = array();

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("0", $timing_info);

		$omode = db_escape_string($_REQUEST["omode"]);

		$feed = db_escape_string($_REQUEST["feed"]);
		$method = db_escape_string($_REQUEST["m"]);
		$view_mode = db_escape_string($_REQUEST["view_mode"]);
		$limit = (int) get_pref($this->link, "DEFAULT_ARTICLE_LIMIT");
		@$cat_view = $_REQUEST["cat"] == "true";
		@$next_unread_feed = db_escape_string($_REQUEST["nuf"]);
		@$offset = db_escape_string($_REQUEST["skip"]);
		@$vgroup_last_feed = db_escape_string($_REQUEST["vgrlf"]);
		$order_by = db_escape_string($_REQUEST["order_by"]);

		if (is_numeric($feed)) $feed = (int) $feed;

		/* Feed -5 is a special case: it is used to display auxiliary information
		 * when there's nothing to load - e.g. no stuff in fresh feed */

		if ($feed == -5) {
			print json_encode(generate_dashboard_feed($this->link));
			return;
		}

		$result = false;

		if ($feed < -10) {
			$label_feed = -11-$feed;
			$result = db_query($this->link, "SELECT id FROM ttrss_labels2 WHERE
							id = '$label_feed' AND owner_uid = " . $_SESSION['uid']);
		} else if (!$cat_view && is_numeric($feed) && $feed > 0) {
			$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
							id = '$feed' AND owner_uid = " . $_SESSION['uid']);
		} else if ($cat_view && is_numeric($feed) && $feed > 0) {
			$result = db_query($this->link, "SELECT id FROM ttrss_feed_categories WHERE
							id = '$feed' AND owner_uid = " . $_SESSION['uid']);
		}

		if ($result && db_num_rows($result) == 0) {
			print json_encode(generate_error_feed($this->link, __("Feed not found.")));
			return;
		}

		/* Updating a label ccache means recalculating all of the caches
		 * so for performance reasons we don't do that here */

		if ($feed >= 0) {
			ccache_update($this->link, $feed, $_SESSION["uid"], $cat_view);
		}

		set_pref($this->link, "_DEFAULT_VIEW_MODE", $view_mode);
		set_pref($this->link, "_DEFAULT_VIEW_LIMIT", $limit);
		set_pref($this->link, "_DEFAULT_VIEW_ORDER_BY", $order_by);

		if (!$cat_view && is_numeric($feed) && $feed > 0) {
			db_query($this->link, "UPDATE ttrss_feeds SET last_viewed = NOW()
							WHERE id = '$feed' AND owner_uid = ".$_SESSION["uid"]);
		}

		$reply['headlines'] = array();

		if (!$next_unread_feed)
			$reply['headlines']['id'] = $feed;
		else
			$reply['headlines']['id'] = $next_unread_feed;

		$reply['headlines']['is_cat'] = (bool) $cat_view;

		$override_order = false;

		if (get_pref($this->link, "SORT_HEADLINES_BY_FEED_DATE", $owner_uid)) {
			$date_sort_field = "updated";
		} else {
			$date_sort_field = "date_entered";
		}

		switch ($order_by) {
			case "date":
				if (get_pref($this->link, 'REVERSE_HEADLINES', $owner_uid)) {
					$override_order = "$date_sort_field";
				} else {
					$override_order = "$date_sort_field DESC";
				}
				break;

			case "title":
				if (get_pref($this->link, 'REVERSE_HEADLINES', $owner_uid)) {
					$override_order = "title DESC, $date_sort_field";
				} else {
					$override_order = "title, $date_sort_field DESC";
				}
				break;

			case "score":
				if (get_pref($this->link, 'REVERSE_HEADLINES', $owner_uid)) {
					$override_order = "score, $date_sort_field";
				} else {
					$override_order = "score DESC, $date_sort_field DESC";
				}
				break;
		}

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("04", $timing_info);

		$ret = $this->format_headlines_list($feed, $method,
			$view_mode, $limit, $cat_view, $next_unread_feed, $offset,
			$vgroup_last_feed, $override_order, true);

		$topmost_article_ids = $ret[0];
		$headlines_count = $ret[1];
		$returned_feed = $ret[2];
		$disable_cache = $ret[3];
		$vgroup_last_feed = $ret[4];

		$reply['headlines']['content'] =& $ret[5]['content'];
		$reply['headlines']['toolbar'] =& $ret[5]['toolbar'];

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("05", $timing_info);

		$reply['headlines-info'] = array("count" => (int) $headlines_count,
						"vgroup_last_feed" => $vgroup_last_feed,
						"disable_cache" => (bool) $disable_cache);

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("20", $timing_info);

		if (is_array($topmost_article_ids) && !get_pref($this->link, 'COMBINED_DISPLAY_MODE') && !$_SESSION["bw_limit"]) {
			$articles = array();

			foreach ($topmost_article_ids as $id) {
				array_push($articles, format_article($this->link, $id, false));
			}

			$reply['articles'] = $articles;
		}

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("30", $timing_info);

		$reply['runtime-info'] = make_runtime_info($this->link);

		print json_encode($reply);

	}
}
?>
