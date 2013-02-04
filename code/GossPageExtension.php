<?php

class GossPageExtension extends DataExtension {

	static $done = false;

	/**
	 * Called on a dev/build, this generates a file containing metadata from the application that is used
	 * by the golang goss library to query this database.
	 *
	 * This function is called multiple times in a dev/build. We only generate the metadata on the first run.
	 *
	 * @return void
	 */
	function requireDefaultRecords() {
		if (self::$done) return;

		// Generate the data structure
		$metadata = $this->generateMetadata();

		// Write to a file
		$this->writeMetadata($metadata);

		self::$done = true;
	}

	/**
	 * Generate a PHP object structure that represents the metadata.
	 * @return void
	 */
	function generateMetadata() {
		$result = new stdClass();
		$result->classes = $this->getClasses();
		return $result;
	}

	function getClasses() {
		// Get all DataObject derivatives.
		$allDbClass = ClassInfo::subclassesFor("DataObject");

		$result = array();

		foreach ($allDbClass as $class) {
			$ci = new stdClass();
			$ci->ClassName = $class;
			$ci->HasTable = ClassInfo::hasTable($class);
			$ci->Versioned = Object::has_extension($class, "Versioned");
			if ($ci->HasTable) $ci->TableName = $ci->Versioned ? $ci->ClassName . "_Live" :  $ci->ClassName;

			$ancestors = ClassInfo::ancestry($class);
			unset($ancestors["Object"]);
			unset($ancestors["ViewableData"]);
			unset($ancestors["DataObject"]);

			// Certain test classes in the framework are DataObject derivatives with no tables. We're not interested.
			$ancestors = array_values($ancestors);
//			if (count($ancestors) == 1 && !$ci->HasTable) continue;
			$ci->Ancestors = $ancestors;

			$subclasses = ClassInfo::subclassesFor($class);
			array_shift($subclasses);
			$ci->Descendents = array_values($subclasses);
//			$ci->Fields = $fieldList;
			$result[] = $ci;
		}
		print_r($result);

		return $result;
//		$top = array();
//		foreach ($allDbClass as $class) {
//			if ($class == "DataObject") continue;
//			$ans = ClassInfo::ancestry($class);
//			array_pop($ans);  // drop off this class
//			$parent = array_pop($ans);
//			if ($parent != "DataObject") continue;
//			$top[] = $class;
//		}

		// $top now contains all the classes that descend directly from DataObject. For each of these classes,
		// we now need to construct the metadata object, and recursively descend for all the subclasses.
	}

	/**
	 * Write the metadata structure to the file system.
	 * The file is stored under assets, as it's the only writable place.
	 * 
	 * @param $metadata
	 * @return void
	 */
	function writeMetadata($metadata) {
		$folder = Folder::find_or_make("goss");
		$file = Director::baseFolder() . "/" . $folder->Filename . "metadata.json";
		file_put_contents($file, json_encode($metadata));
		$folder->syncChildren();
	}

}
