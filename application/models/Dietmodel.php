<?php

class Dietmodel extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    function check($id) {
        return $this->db
                        ->select('*')
                        ->from("diet_pricelist")
                        ->where("id", $id)
                        ->get()
                        ->result();
    }

    function cart($cart) {
        if (!is_array($cart))
            return array();
        $r = array();
        $p = 0;
        foreach ($cart as $id => $order) {
            $d = $this->db
                    ->select('*')
                    ->from("diet_pricelist")
                    ->where("id", $order->id)
                    ->get()
                    ->result();

            if ($d == null)
                continue;
            $d = $d[0];

            $price = $d->price / 100;
            $days = $order->weekend ? $d->days : 0;
            $weekdays = $order->weekend ? 0 : $d->days;

            $d = $this->db
                    ->select('*')
                    ->from("diets")
                    ->where("id", $d->diet)
                    ->get()
                    ->result();

            if ($d == null)
                continue;
            $d = $d[0];

            $name = $d->name . " " . $d->calories . " KCAL";

            $date = DateTime::createFromFormat('Y-m-d', $order->from);
            $now = new DateTime();
            $from = max($date, $now);

            $date = DateTime::createFromFormat('Y-m-d', $order->from);
            $now = new DateTime();
            $to = max($date, $now);
            $to->modify("+$days day");
            $to->modify("+$weekdays weekday");

            $i = new stdClass();
            $i->id = $id;
            $i->label = $name;
            $i->quantity = $order->number;
            $i->begin = $from->format('Y-m-d');
            $i->end = $to->format('Y-m-d');
            $p += $price * $order->number;
            $i->cost = $order->number . " x " . sprintf("%01.2f", $price) . " PLN";
            $i->unitprice = sprintf("%01.2f", $price);

            array_push($r, $i);
        }

        return array('cost' => sprintf("%01.2f", $p), 'cart' => $r);
    }

    function getEnergy($name = '') {
        $data = $this->db
                ->select('id,calories')
                ->from('diets')
                ->where('name', $name)
                ->get()
                ->result();
        $o = array();
        foreach ($data as $d)
            $o[$d->id] = $d->calories;
        return $o;
    }

    function get($id) {
        $diet = $this->db
                ->select('*')
                ->from("diets")
                ->where('id', $id)
                ->get()
                ->result();

        if ($diet == null)
            return null;
        if (count($diet) != 1)
            return null;

        $key = $diet[0]->name;

        $d = $this->db
                ->select('diet_pricelist.*, diets.calories, diets.name as "label"')
                ->from("diet_pricelist")
                ->join("diets", "diets.id = diet_pricelist.diet", "left")
                ->like("diets.name", $key)
                ->get()
                ->result();

        $r = array();

        foreach ($d as $v) {
            if (!array_key_exists($v->name, $r))
                $r[$v->name] = array();

            $v->price = sprintf("%.2F", $v->price / 100);
            $r[$v->name][$v->calories] = $v;
        }
        return array('name' => $key, 'diet' => $r);
    }

    function add($new, $data, $id = 0) {
        //print_r($data);
        /*   $d = $this->db
          ->like("name", $data['name'] )
          ->delete("diets");

          $ids = array(); */
        $energy = $data['energy'];

        foreach ($data['price'] as $k => $row) {
            reset($energy);
            $kcal = current($energy);
            foreach ($row as $i => $price) {

                unset($data['price'][$k][$i]);
                $data['price'][$k][$kcal] = $price;
                $kcal = next($energy);
            }
        }


        if ($new) {
            foreach ($data['price'][0] as $kcal => $price) {
                $this->db->insert(
                        'diets', array(
                    'name' => $data['name'],
                    'calories' => $kcal
                        )
                );

                $id = $this->db->insert_id();

                foreach ($data['period'] as $n => $period)
                    if ($data['price'][$n][$kcal] > 0)
                        $this->db->insert(
                                'diet_pricelist', array(
                            'diet' => $id,
                            'name' => $period,
                            'days' => $data['days'][$n],
                            'price' => ceil($data['price'][$n][$kcal] * 100)
                                )
                        );
            }
        }

        else {
            $d = $this->db
                    ->select('*')
                    ->from("diets")
                    ->like("name", $data['name'])
                    ->get()
                    ->result();
            reset($energy);
            $kcal = current($energy);
            foreach ($d as $diet) {
                $diet->calories = $kcal;

                foreach ($data['period'] as $n => $period)
                    $this->db
                            ->where('diet', $diet->id)
                            ->where('name', $period)
                            ->where('days', $data['days'][$n])
                            ->update(
                                    'diet_pricelist', array(
                                     'price' => ceil($data['price'][$n][$diet->calories] * 100)
                                    )
                    );
                $this->db->where('id', $diet->id)->update('diets', array('calories' => $kcal));
                $kcal = next($energy);
            }
            $this->db->where('id', $diet->id)->update('diets',array('name'=>$data['nname']));
        }
        return $id;
    }

    function getDietName($id) {
        $res = $this->db->select('name')->from('diets')->where('id', $id)->get()->result();
        return $res[0]->name;
    }

    function getDietIdsByName($name) {
        $res = $this->db->select('id')->from('diets')->where('name', $name)->get()->result();

        $o = array();
        foreach ($res as $r)
            $o[] = $r->id;
        return $o;
    }

    function remove($id = 0) {
        $name = $this->getDietName($id);
        $ids = $this->getDietIdsByName($name);

        $this->db->delete('diets', array('name' => $name));
        $this->db->where_in('diet', $ids);
        $this->db->delete('diet_pricelist');
    }

    function getDiets() {
        $diets = $this->db
                ->select('*')
                ->from('diets')
                ->group_by('name')
                ->having('id = MIN(id)')
                ->order_by('name ASC')
                ->get()
                ->result();
        return array('diets' => $diets);
    }

    function all() {
        $diets = $this->db
                ->select('*')
                ->from("diets")
                ->order_by("name, calories", "asc")
                ->get()
                ->result();
        $r = array(
            'diets' => array(),
            'prices' => array()
        );

        foreach ($diets as $diet) {
            if (!array_key_exists($diet->name, $r['diets']))
                $r['diets'][$diet->name] = array();

            array_push($r['diets'][$diet->name], $diet);
        }

        $diets = $this->db
                ->select('*')
                ->from("diet_pricelist")
                ->get()
                ->result();


        foreach ($diets as $diet) {
            if (!array_key_exists($diet->diet, $r['prices']))
                $r['prices'][$diet->diet] = array();

            array_push($r['prices'][$diet->diet], $diet);
        }

        foreach ($r['diets'] as $ak => $a) {
            foreach ($a as $k => $d) {
                if (!array_key_exists($d->id, $r['prices']))
                    unset($r['diets'][$ak][$k]);
                else
                    $r['diets'][$ak][$k] = $d;
            }

            if (count($r['diets'][$ak]) == 0)
                unset($r['diets'][$ak]);
        }

        //echo '<pre>'; print_r($r);exit;

        return $r;
    }

}
