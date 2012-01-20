<?php
/**
 * Create_Mptt_Sql
 *
 * @package
 * @version 0.1
 * @copyright
 * @author Nobuhiko
 * @license
 */
class Create_Mptt_Sql {

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
     * ルートのidを返す
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
     * Tree構造を作成する
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

        // 子供のみ取得したい場合
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
     * パンクズの作成（下から上をたどる処理）
     *
     * @param mixed $direct_parent_only 直接の親のみ取得する場合 true
     * @param mixed $exclusion_id 除外するid
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

        // 自分の親は必ず1階層上
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
     * selectbox等で使いやすいように id => name のリストを作成する
     *
     * @param string $separator levelの回数セパレーターを挿入する
     * @param mixed $exclusion_id 除外するid
     * @access public
     * @return string
     */
    function getSelectedList($separator = ' ― ', $exclusion_id = NULL) {

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
     * 葉のデータを取得する
     * (親のidを常に取ったほうが効率が良い)
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
     * 親の末に子を追加する(為に場所を空ける)
     *
     * @param mixed $rgt 親のright
     * @access private
     * @return string
     */
    private function blankAsLastChildOf($rgt) {

        // 親の末席を空けるSQL
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
     * 指定したidの前に挿入する(為に場所を空ける)
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
     * 指定したグループを入れ替える
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
     * 親の末へ移動する
     *
     * @param mixed $group_id 親のid
     * @param mixed $level 親のlevel
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
     * 指定した兄弟の前に移動する
     *
     * @param mixed $group_id 兄弟のid
     * @param mixed $level 兄弟のlevel
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
     * 子供も含めて削除する
     *
     * @param mixed $left 削除する対象のleft
     * @param mixed $right 削除する対象のright
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

        // 順番をずらすスタート
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
