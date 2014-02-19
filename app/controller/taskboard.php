<?php

namespace Controller;

class Taskboard extends Base {

	public function index($f3, $params) {
		$this->_requireLogin();

		// Load the requested sprint
		$sprint = new \Model\Sprint();
		$sprint->load($params["id"]);
		if(!$sprint->id) {
			$f3->error(404);
			return;
		}
		$f3->set("sprint", $sprint);
		$f3->set("title", $sprint->name);

		// Load issue statuses
		$status = new \Model\Issue\Status();
		$statuses = $status->find();
		$f3->set("statuses", $statuses);

		// Load issue priorities
		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find());

		// Load project list
		$issue = new \Model\Issue\Detail();
		$projects = $issue->find(array("sprint_id = ? AND deleted_date IS NULL AND type_id = ?", $sprint->id, $f3->get("issue_type.project")), array("order" => "owner_id ASC"));

		// Build multidimensional array of all tasks and projects
		$taskboard = array();
		foreach($projects as $project) {

			// Build array of statuses to put tasks under
			$columns = array();
			foreach($statuses as $status) {
				$columns[$status["id"]] = array();
			}

			// Get all tasks under the project, put them under their status
			$tasks = $issue->find(array("parent_id = ? AND type_id = ? AND deleted_Date IS NULL", $project["id"], $f3->get("issue_type.task")), array("order" => "priority DESC, has_due_date ASC, due_date ASC"));
			foreach($tasks as $task) {
				$columns[$task["status"]][] = $task;
			}

			// Add hierarchial structure to taskboard array
			$taskboard[] = array(
				"project" => $project,
				"columns" => $columns
			);

		}
		$f3->set("taskboard", $taskboard);

		// Get user list for select
		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		echo \Template::instance()->render("taskboard/index.html");

	}

	public function add($f3, $params) {
		$user_id = $this->_requireLogin();
		$post = $f3->get("POST");

		$issue = new \Model\Issue();
		$issue->name = $post["title"];
		$issue->description = $post["description"];
		$issue->author_id = $user_id;
		$issue->owner_id = $post["assigned"];
		$issue->created_date = now();
		$issue->hours_total = $post["hours"];
		$issue->hours_remaining = $post["hours"];
		if(!empty($post["dueDate"])) {
			$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
		}
		$issue->priority = $post["priority"];
		$issue->parent_id = $post["storyId"];
		$issue->save();

		print_json($issue->cast() + array("taskId" => $issue->id));
	}

	public function edit($f3, $params) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["taskId"]);
		if(!empty($post["receiver"])) {
			$issue->parent_id = $post["receiver"]["story"];
			$issue->status = $post["receiver"]["status"];
			$status = new \Model\Issue\Status();
			$status->load($issue->status);
			if($status->closed) {
				$issue->closed_date = now();
			}
		} else {
			$issue->name = $post["title"];
			$issue->description = $post["description"];
			$issue->owner_id = $post["assigned"];
			$issue->hours_remaining = $post["hours"];
			if(!empty($post["dueDate"])) {
				$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
			} else {
				$issue->due_date = null;
			}
			$issue->priority = $post["priority"];
			if(!empty($post["storyId"])) {
				$issue->parent_id = $post["storyId"];
			}
			$issue->title = $post["title"];
		}
		$issue->save();

		print_json($issue->cast() + array("taskId" => $issue->id));
	}

}