<?php
/**
 * Cell containing the name of an object which is also a link to that object.
 *
 */
class DP_View_Cell_ObjectLink extends DP_View_Cell {
	protected $hrefprefix;
	protected $id_key;
	protected $name_key;
	
	public function __construct($id_key, $name_key, $hrefprefix, $column_title = '(Untitled)') {
		// TODO - proper generation of parent id
		parent::__construct($name_key, $column_title);
		$this->id_key = $id_key;
		$this->name_key = $name_key;
		$this->hrefprefix = $hrefprefix;
	}
	
	/**
	 * Render the cell with a given associative array produced from a data source.
	 * 
	 * The data source will produce an associative array of table column name to value.
	 * @param array $rowhash associative array of row data.
	 * @return HTML output
	 */
	public function render($rowhash) {
		$output = '<a href="'.$this->hrefprefix.$rowhash[$this->id_key].'">';
		$output .= $rowhash[$this->name_key];
		$output .= '</a>';

		return $output;
	}
}
?>