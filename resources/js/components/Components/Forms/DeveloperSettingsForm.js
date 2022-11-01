import React, { Component } from 'react';
import {
  Form,
  Input,
  Button,
  Row,
  Col, message,
} from 'antd/lib/index';
import {
  CopyOutlined
} from "@ant-design/icons";


class DeveloperSettingsForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };

  copyText = () => {
    if (typeof(navigator.clipboard) != 'undefined') {
      navigator.clipboard.writeText(this.props.publicToken).then(function() {
        message.success('API Token copied!');
      }, function(err) {

      });
    }
  }


  render() {
    const apiLink = process.env.MIX_TEELAUNCH_API;

    const {
      handleGenerateToken,
      handleRevokeToken,
      publicToken,
    } = this.props
    return (
      <>
      <Row style={{ marginBottom: '20px'}}>
        <Col>
          <div style={{ display: 'flex', alignItems: 'center' }}>
            <div style={{ fontSize: '22px', padding: '6px 0 0px 10px', paddingRight: '10px', width:'16.5%' }}>
              API Token
            </div>
            <Input.Password
              type="text"
              placeholder="No API Token Generated"
              readOnly={true}
              value={publicToken}
              ref="tokenValue"
            />
            <Button style={{
              border: 'none',
              boxShadow: 'none'
            }}
                    onClick={() =>  this.copyText() }
            >
              <CopyOutlined/>
            </Button>
          </div>
          <div style={{ fontSize: '14px', paddingLeft: '10px' }}>
             Use the api token to use our standalone api solution, for more details see documentation <a href={apiLink+'/documentation'} target='_blank'>here</a>.
          </div>
        </Col>
      </Row>
      <Row>

        {publicToken ? (
          <Button type="link" onClick={handleRevokeToken}>
            Revoke Token
          </Button>
        ) : (
          <Button type="link" onClick={handleGenerateToken}>
            Generate Token
          </Button>
        )}
      </Row>
      </>
    );
  }
}

export default Form.create({ name: 'developer_settings' })(DeveloperSettingsForm);
