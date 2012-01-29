<?php
/**
 * Mptt
 *
 * @package
 * @version 0.1
 * @copyright
 * @author Nobuhiko
 * @license
 */
class Mptt {

    public function __construct() {

        $this->properties = array(
            'table_name'    => '',
            'id'            => '',
            'name'          => '',
            'left'          => '',
            'right'         => '',
            'level'         => '',
        );
    }

    /**
     * �롼�Ȥ�id���֤�
     *
     * @access public
     * @return void
     */
    public function getRootId() {

        return "
            SELECT
                " . $this->properties['id'] . "
            FROM
                " . $this->properties['table_name'] . "
            WHERE
                    " . $this->properties['left'] . " = ?
            ";
    }

    /**
     * Tree��¤����������
     *
     * @param mixed $direct_children_only
     * @access public
     * @return string
     */
    public function getTree($direct_children_only = false) {

        $sql    = "
            SELECT
                 Child.*
            FROM
                " . $this->properties['table_name'] . " AS Parent
            INNER JOIN
                " . $this->properties['table_name'] . " AS Child
            ON
                    Child." . $this->properties['left'] . " > Parent." . $this->properties['left'] . "
                AND Child." . $this->properties['right'] . " < Parent." . $this->properties['right'] . "
        ";

        // �Ҷ��Τ߼�������������
        if ($direct_children_only === true) {
            $sql .= "
                AND Child." . $this->properties['level'] . " = Parent." . $this->properties['level'] . " + 1
                ";
        }

        $sql .= "
            WHERE
                    Parent." . $this->properties['id'] . " = ?
            ORDER BY Child." . $this->properties['left'] . "
            ";

        return $sql;
    }


    /**
     * �ѥ󥯥��κ����ʲ��������򤿤ɤ�������
     *
     * @param mixed $direct_parent_only ľ�ܤοƤΤ߼����������� true
     * @param mixed $exclusion_id ��������id
     * @access public
     * @return string
     */
    public function getPath($direct_parent_only = false, $exclusion_id = NULL) {

        $sql    = "
            SELECT
                Parent.*
            FROM
                " . $this->properties['table_name'] . " AS Child
            INNER JOIN
                " . $this->properties['table_name'] . " AS Parent
            ON
                    Parent." . $this->properties['left'] . " < Child." . $this->properties['left'] . "
                AND Parent." . $this->properties['right'] . " > Child." . $this->properties['right'] . "
        ";

        // ��ʬ�οƤ�ɬ��1���ؾ�
        if ($direct_parent_only === true) {
            $sql .= "
                AND Parent." . $this->properties['level'] . " = Child." . $this->properties['level'] . " - 1
            ";
        }

        $sql .= "
            WHERE
                Child." . $this->properties['id'] . " = ?";

        if ($exclusion_id) {
            $sql .= "
                AND Parent." . $this->properties['id'] . " <> " . $exclusion_id;
        }

        $sql .= "
            ORDER BY Parent." . $this->properties['left'] . "
            ";

        return $sql;
    }


    /**
     * selectbox���ǻȤ��䤹���褦�� id => name �Υꥹ�Ȥ���������
     *
     * @param string $separator level�β������ѥ졼��������������
     * @param mixed $exclusion_id ��������id
     * @access public
     * @return string
     */
    function getSelectedList($separator = ' �� ', $exclusion_id = NULL) {

        $sql    = "
            SELECT
                 Child." . $this->properties['id'] . "
                ,CONCAT(REPEAT( '". $separator ."', Child." . $this->properties['level'] . " - 1), Child." . $this->properties['name'] . ") AS " . $this->properties['name'] . "
            FROM
                " . $this->properties['table_name'] . " AS Parent
            INNER JOIN
                " . $this->properties['table_name'] . " AS Child
            ON
                    Child." . $this->properties['left'] . " >= Parent." . $this->properties['left'] . "
                AND Child." . $this->properties['right'] . " <= Parent." . $this->properties['right'] . "
            WHERE
                    Parent." . $this->properties['id'] . " = ?
            ";

        if ($exclusion_id) {
            $sql .= "
                AND Child." . $this->properties['id'] . " <> " . $exclusion_id;
        }

        $sql .= "
            ORDER BY Child." . $this->properties['left'];

        return $sql;
    }

    /**
     * �դΥǡ�������������
     * (�Ƥ�id�����˼��ä��ۤ�����Ψ���ɤ�)
     *
     * @access public
     * @return string
     */
    public function getLeaf() {

        $sql = "
			 SELECT
			     " . $this->properties['id'] . "
			    ," . $this->properties['name'] . "
			    ," . $this->properties['left'] . "
			    ," . $this->properties['right'] . "
			    ," . $this->properties['level'] . "
			 FROM
			     " . $this->properties['table_name'] . "
			 WHERE
			     " . $this->properties['table_name'] . "." . $this->properties['id'] . "   = ?
             ";

        return $sql;
    }

    /**
     * �Ƥ����˻Ҥ��ɲä���(�٤˾�����������)
     *
     * @param mixed $rgt �Ƥ�right
     * @access private
     * @return string
     */
    private function blankAsLastChildOf($rgt) {

        // �Ƥ����ʤ�������SQL
        return "
            UPDATE " . $this->properties['table_name'] . "
                SET  " . $this->properties['left'] . " = CASE WHEN " . $this->properties['left'] . " > $rgt
                                    THEN " . $this->properties['left'] . " + 2
                                    ELSE " . $this->properties['left'] . " END
                    ," . $this->properties['right'] . " = CASE WHEN " . $this->properties['right'] . " >= $rgt
                                    THEN " . $this->properties['right'] . " + 2
                                    ELSE " . $this->properties['right'] . " END
                WHERE
                        " . $this->properties['right'] . " >= $rgt
            ";

    }


    /**
     * ���ꤷ��id��������������(�٤˾�����������)
     *
     * @param mixed $left
     * @param mixed $right
     * @access private
     * @return string
     */
    private function blankAsPrevChildOf($left, $right) {

        return "
            UPDATE " . $this->properties['table_name'] . "
                SET  " . $this->properties['left'] . " = CASE WHEN " . $this->properties['left'] . " >= $left
                                    THEN " . $this->properties['left'] . " + 2
                                    ELSE " . $this->properties['left'] . " END
                    ," . $this->properties['right'] . " = CASE WHEN " . $this->properties['right'] . " >= $left
                                    THEN " . $this->properties['right'] . " + 2
                                    ELSE " . $this->properties['right'] . " END
                WHERE
                        " . $this->properties['right'] . " >= $right
            ";
    }

    /**
     * ���ꤷ�����롼�פ������ؤ���
     *
     * @param mixed $left_1
     * @param mixed $right_1
     * @param mixed $left_2
     * @param mixed $right_2
     * @access private
     * @return string
     */
    private function replaceLeaf($left_1, $right_1, $left_2, $right_2) {

        return "
            UPDATE " . $this->properties['table_name'] . " SET
                " . $this->properties['left'] . " = CASE WHEN " . $this->properties['left'] . " BETWEEN $left_1 AND $right_1 THEN $right_2 + " . $this->properties['left'] . " - $right_1 WHEN " . $this->properties['left'] . " BETWEEN $left_2 AND $right_2 THEN $left_1 + " . $this->properties['left'] . " - $left_2 WHEN " . $this->properties['left'] . " BETWEEN $right_1 + 1 AND $left_2 - 1 THEN $left_1 + $right_2 + " . $this->properties['left'] . " - $right_1 - $left_2 ELSE " . $this->properties['left'] . " END,
                " . $this->properties['right'] . " = CASE WHEN " . $this->properties['right'] . " BETWEEN $left_1 AND $right_1 THEN $right_2 + " . $this->properties['right'] . " - $right_1 WHEN " . $this->properties['right'] . " BETWEEN $left_2 AND $right_2 THEN $left_1 + " . $this->properties['right'] . " - $left_2 WHEN " . $this->properties['right'] . " BETWEEN $right_1 + 1 AND $left_2 - 1 THEN $left_1 + $right_2 + " . $this->properties['right'] . " - $right_1 - $left_2 ELSE " . $this->properties['right'] . " END
            WHERE
                    " . $this->properties['left'] . " BETWEEN $left_1 AND $right_2
                AND $left_1 < $right_1
                AND $right_1 < $left_2
                AND $left_2 < $right_2
            ";
    }


    /**
     * �Ƥ����ذ�ư����
     *
     * @param mixed $group_id �Ƥ�id
     * @param mixed $level �Ƥ�level
     * @access public
     * @return string
     */
    public function moveAsLastChildOf($group_id, $level) {

        return "
            UPDATE
                " . $this->properties['table_name'] . " Object
            INNER JOIN " . $this->properties['table_name'] . " Shift
                ON Shift." . $this->properties['left'] . " >= Object." . $this->properties['left'] . "
                AND Shift." . $this->properties['right'] . " <= Object." . $this->properties['right'] . "
            INNER JOIN " . $this->properties['table_name'] . " Target
                ON Target." . $this->properties['id'] . " = $group_id
            SET
                 Shift." . $this->properties['left'] . " = Shift." . $this->properties['left'] . " + (Target." . $this->properties['right'] . " - 1 - Object." . $this->properties['right'] . ")
                ,Shift." . $this->properties['right'] . " = Shift." . $this->properties['right'] . " + (Target." . $this->properties['right'] . " - 1 - Object." . $this->properties['right'] . ")
                ,Shift." . $this->properties['level'] . " = Shift." . $this->properties['level'] . " + ($level + 1 - Object." . $this->properties['level'] . ")
            WHERE
                    Object." . $this->properties['id'] . " = ?
            ";
    }


    /**
     * ���ꤷ�����������˰�ư����
     *
     * @param mixed $group_id ������id
     * @param mixed $level ������level
     * @access public
     * @return string
     */
    public function moveAsPrevChildOf($group_id, $level) {

        return "
            UPDATE
                " . $this->properties['table_name'] . " Object
            INNER JOIN " . $this->properties['table_name'] . " Shift
                ON Shift." . $this->properties['left'] . " >= Object." . $this->properties['left'] . "
                AND Shift." . $this->properties['right'] . " <= Object." . $this->properties['right'] . "
            INNER JOIN " . $this->properties['table_name'] . " Target
                ON Target." . $this->properties['id'] . " = $group_id
            SET
                 Shift." . $this->properties['left'] . " = Shift." . $this->properties['left'] . " + (Target." . $this->properties['left'] . " - 1 - Object." . $this->properties['right'] . ")
                ,Shift." . $this->properties['right'] . " = Shift." . $this->properties['right'] . " + (Target." . $this->properties['left'] . " - 1 - Object." . $this->properties['right'] . ")
                ,Shift." . $this->properties['level'] . " = Shift." . $this->properties['level'] . " + ($level + 1 - Object." . $this->properties['level'] . ")
            WHERE
                    Object." . $this->properties['id'] . " = '" . $data['group_id'] . "'
            ";
    }



    /**
     * �Ҷ����ޤ��ƺ�������
     *
     * @param mixed $left ���������оݤ�left
     * @param mixed $right ���������оݤ�right
     * @access public
     * @return array
     */
    public function delete($left, $right) {

        $deletSql = "
            DELETE FROM
                " . $this->properties['table_name'] . "
            WHERE
                " . $this->properties['left'] . " >= " . $lft . " AND " . $this->properties['right'] . " <= " . $rgt
            ;

        // ���֤򤺤餹��������
        $target_rl_difference = "$right  - $left + 1";

        $leftSql = "
            UPDATE
                " . $this->properties['table_name'] . "
            SET
                " . $this->properties['left'] . " = " . $this->properties['left'] . " - " . $target_rl_difference . "
            WHERE
                " . $this->properties['left'] . " > " . $left;


        $rightSql = "
            UPDATE
                " . $this->properties['table_name'] . "
            SET
                " . $this->properties['right'] . " = " . $this->properties['right'] . " - " . $target_rl_difference . "
            WHERE
                " . $this->properties['right'] . " > " . $left;

        return array($deletSql, $leftSql, $rightSql);
    }
}
