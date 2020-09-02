#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
if ($issue = getBranchIssue()) {
  $node_info = getEntityInfo(getBranchIssue());

  $node_info = (array) $node_info;
  $important_keys = ['title', 'field_issue_component', 'field_issue_version', 'comment_count', 'flag_tracker_follower_count'];
  $important = array_intersect_key($node_info, array_flip($important_keys));
  $important['last updated'] = getTimeFromTimeStamp($node_info['field_issue_last_status_change']);
  $important['status'] = getIssueStatus($node_info['field_issue_status']);
  //$important['created by'] = $node_info['author'];
  $last_comment = (array) getEntityInfo($node_info['comments'][$important['comment_count'] - 1]->id, 'comment');
  $important_keys = ['name'];
  $important_last_comment = array_intersect_key($last_comment, array_flip($important_keys));
  $important_last_comment['created'] =getTimeFromTimeStamp($last_comment['created']);

  $important['last comment'] = $important_last_comment;
  $my_uid = getSetting('my_user_id');
  if ($last_comment['author']->id != $my_uid) {
    // Get my last comment
    $comments = (array) json_decode(file_get_contents("https://www.drupal.org/api-d7/comment.json?node={$node_info['nid']}&author=$my_uid"))->list;
    $important['my comment count'] = count($comments);
    $comment = array_pop($comments);
    $important['my last comment'] = getTimeFromTimeStamp($comment->created);
    //$important['my last comment'] = $important_last_comment['created'] = date("Y-m-d H:i:s", $comment->created);

  }
  print "⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐\n";
  print_r($important);
  print "⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐⭐\n";

}



