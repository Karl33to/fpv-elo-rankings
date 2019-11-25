<?php

class ELOPlayer
{
  public $name       = "";
  public $place      = 0;
  public $elo_pre    = 0;
  public $elo_post   = 0;
  public $elo_change = 0;
  public $num_games  = 0;
}

class ELOMatch
{
  public $players = array();

  public function addPlayer($name, $place, $elo, $num_games)
  {
    $player = new ELOPlayer();

    $player->name       = $name;
    $player->place      = $place;
    $player->elo_pre    = $elo;
    $player->num_games  = $num_games;

    $this->players[] = $player;
  }

  public function getELO($name)
  {
    foreach ($this->players as $p){
      if ($p->name == $name){
        return $p->eloPost;
      }
    }
    return 1000;
  }
  
  public function getELOChange($name){
    foreach ($this->players as $p){
      if ($p->name == $name){
        return $p->elo_change;
      }
    }
    return 0;
  }

  public function calculateELOs($base_elo, $beta, $k_factor) {
    $n = count($this->players);

    // work out the average ELO gap between the competitors at the start of the competition
    // this will be used to give new players a starting rank
    $elo_min = $base_elo;
    $elo_max = $base_elo;
    for ($i = 0; $i < $n; $i++){
      if($this->players[$i]->elo_pre > $elo_max){
        $elo_max = $this->players[$i]->elo_pre;
      }
      if($this->players[$i]->elo_pre < $elo_min){
        $elo_min = $this->players[$i]->elo_pre;
      }
    }
    $elo_gap = round(($elo_max - $elo_min) / $n);

    // for every player, calculate their ELO vs all of the other competitors
    for ($i = 0; $i < $n; $i++){
      $curPlace = $this->players[$i]->place;
      $curELO   = $this->players[$i]->elo_pre;

      // this is a pilots first race, so give them a starting rank
      if ($this->players[$i]->num_games === 0) {
        // based on their finishing position and the ranks of
        // all the other competitors going into the competition
        // this is handy when a good pilot from out of town races as they wont be
        // starting from scratch and upset the system by scoring loads of points
        // in other systems (chess) this is done by completing 10 ranking matches
        // before being entered into the system
        $curELO = round(($elo_gap * ($n - $this->players[$i]->place) + $elo_min));
        // the other alternative is to give them a starting ELO of 1000
        // this will maintain the points equilibrium of 1000 per cometitor, 
        // but also potentially cause upsets when unranked pilots score highly
        // $curELO = 1000;
        $this->players[$i]->elo_pre = $curELO;
      }

      // work through all this players competitors
      for ($j = 0; $j < $n; $j++){
        // skip themselves
        if ($i != $j){
          $opponentPlace = $this->players[$j]->place;
          $opponentELO   = $this->players[$j]->elo_pre;

          // =====================================================
          // K factor is the sensitivity to new results
          // it can be thought of how many points you are betting on this result
          // it needs to scale along with the number of competitors, 
          // so the number of points in the system will maintain equlibrium
          // too low a starting value causes no rank movement
          // too high makes the recent events to carry too much weight
          // a low number of competitors in the pool needs a higher K to make the results actually move
          // this is tuned by eye to find a good middle ground where
          // the historic data can be seen to form part of recent rankings
          $K = $k_factor / ($n - 1);
          // notes on variable K-factor dependant on experience
          // echo "K = [".$K."]<br>";
          // =====================================================

          // =====================================================
          // work out S - this just represents the outcome and is used to adjust the probability
          if ($curPlace < $opponentPlace) {
            $S = 1; // win
          } elseif($curPlace == $opponentPlace) {
            $S = 0.5; // draw
          } else {
            $S = 0; // loss
          }
          // echo "S = [".$S."]<br>";
          // =====================================================

          // =====================================================
          // work out their chance of winning, using the beta as an indication of comparative skill levels
          // $beta represents a 10:1 chance of winning (91%), so a win gets 91% of the k factor, loss = 9% and a draw 50%
          // we can work out the correct $beta value based on past results
          // by plotting them on a histogram and observing the standard deviation
          // that is assuming they follow a normal distribution
          $win_chance = 1 / (1 + pow(10, ($opponentELO - $curELO) / $beta));
          // 1 / (1 + 10^(2400 - 2000) / 400)
          // 1 / (1 + 10^(400 / 400)
          // 1 / (1 + 10^1)
          // 1 / (1 + 10)
          // 1 / (11)
          // 0.0909090909
          // 9%
          // echo "expected_result of " . $opponentELO ." vs ". $curELO . "= [".$win_chance."]<br>";
          // =====================================================

          // =====================================================
          // using the expected win/loss ratio calculate ELO change vs this one opponent, 
          // add it to the total change
          $this->players[$i]->elo_change += (int) round($K * ($S - $win_chance));
          // =====================================================
        }
      }
      // add accumulated change to initial ELO to find their new total ELO   
      $this->players[$i]->eloPost = $this->players[$i]->elo_pre + $this->players[$i]->elo_change;
    }
  }
}

?>