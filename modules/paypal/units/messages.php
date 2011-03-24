<?php
class SubscriptionCheck_Request extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBString";
    $this->values["1"] = "";
    $this->fields["2"] = "PBString";
    $this->values["2"] = "";
    $this->fields["3"] = "PBString";
    $this->values["3"] = "";
  }
  function license()
  {
    return $this->_get_value("1");
  }
  function set_license($value)
  {
    return $this->_set_value("1", $value);
  }
  function custom()
  {
    return $this->_get_value("2");
  }
  function set_custom($value)
  {
    return $this->_set_value("2", $value);
  }
  function item()
  {
    return $this->_get_value("3");
  }
  function set_item($value)
  {
    return $this->_set_value("3", $value);
  }
}
class SubscriptionCheck_Response extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBInt";
    $this->values["1"] = "";
    $this->fields["2"] = "PBBool";
    $this->values["2"] = "";
    $this->fields["3"] = "PBString";
    $this->values["3"] = "";
  }
  function valid_until()
  {
    return $this->_get_value("1");
  }
  function set_valid_until($value)
  {
    return $this->_set_value("1", $value);
  }
  function error()
  {
    return $this->_get_value("2");
  }
  function set_error($value)
  {
    return $this->_set_value("2", $value);
  }
  function error_message()
  {
    return $this->_get_value("3");
  }
  function set_error_message($value)
  {
    return $this->_set_value("3", $value);
  }
}
?>