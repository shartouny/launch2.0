import React, { Component, Fragment } from 'react';
import {Menu, Spin} from 'antd/lib/index';
const { SubMenu } = Menu;

/**
 *
 */
export default class SideNav extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };
  /**
   *
   * @param {{}} category
   * @returns {*}
   */
  buildSubCategories = (category) => {
    const { children } = category;
    
    return (
      <SubMenu title={category.name} key={category.id}>

        <Menu.Item key={category.id}>{category.name}</Menu.Item>
        {children.map(child => (
          <Menu.ItemGroup key={child.id} style={{marginTop: '-20px'}}>
            <Menu.Item
              key={child.id}
              style={{textIndent: '10px'}}
            >
              {child.name}
            </Menu.Item>
            {child.children &&
              child.children.map(nestChild => (
                <Menu.ItemGroup key={nestChild.id} style={{marginTop: '-20px'}}>
                  <Menu.Item
                    key={nestChild.id}
                    style={{textIndent: '20px'}}
                  >
                    {nestChild.name}
                  </Menu.Item>
                  {nestChild.children &&
                    nestChild.children.map(nestedNestedChild => (
                      <Menu.Item
                        key={nestedNestedChild.id}
                        style={{textIndent: '30px'}}
                      >
                        {nestedNestedChild.name}
                      </Menu.Item>
                      ))
                    }
                </Menu.ItemGroup>
              ))
            }
          </Menu.ItemGroup>

        ))}

      </SubMenu>
    );
  };
  /**
   *
   * @returns {*}
   */
  render() {
    const {
      isLoadingSideNav,
      menuItems,
      clickHandler,
      selected,
    } = this.props;
    
    if (isLoadingSideNav) return <Spin />;
    return (
      <Menu
        onClick={clickHandler}
        style={{ width: '100%' }}
        selectedKeys={[selected]}
        mode="inline"
      >
        {/*TODO: This might be custom*/}
        {menuItems.map(item => (
          !item.children ? (
            <Menu.Item key={item.id}>
              {item.name}
            </Menu.Item>
            ) : (
            this.buildSubCategories(item)
          ))
        )}
      </Menu>
    );
  }
}
