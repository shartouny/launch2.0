import React, { Component } from 'react';
import { Link } from 'react-router-dom';

import Logo from './../../Assets/teelaunch-logo-white.svg';

/**
 *
 */
export default class HeaderBrand extends Component {
  render() {
    return (
        <>
          <Link
            to="/catalog"
            style={{ display: 'flex' }}
          >
            <img
              className='brand-image'
              src={Logo}
              alt="Logo"
            />
          </Link>
        </>
    );
  }
}

