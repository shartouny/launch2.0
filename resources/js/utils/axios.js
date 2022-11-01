import React from "react";
import { Redirect } from "react-router-dom";

import tokenService from "./tokenService";

const API_VERSION = "v1";

/**
 *
 * @param {{}} axios
 * @param history
 */
export const axiosConfig = (axios, history) => {
  axios.defaults.baseURL = `/api/${API_VERSION}`;
  axios.defaults.headers.common[
    "Authorization"
  ] = `Bearer ${tokenService.getToken()}`;

  axios.interceptors.response.use(
    res => {
      //redirectResponse(res, history);
      return res;
    },
    error => {
      /**
       * Token expired, delete tokens and redirect to login
       */
      if (error.response.status === 401) {
        tokenExpired(axios);
      }

      if (error.response.status === 403) {
        //emailUnverified();
      }

      if (error.response.status === 404) {
        //redirectNotFound();
      }

      return Promise.reject(error);
    }
  );
};
/**
 *
 * @returns {JSX.Element}
 */
export const tokenExpired = axios => {
  document.cookie = "token=";
  tokenService.deleteToken();
  axiosLogout(axios);
  history.push("/login");
  return <Redirect to={"/login"} />;
};

/**
 *
 * @param {{}} axios
 */
export const axiosLogout = axios => {
  axios.defaults.baseURL = "";
  axios.defaults.headers.common["Authorization"] = "";
};

/**
 *
 * @param res
 * @param history
 */
export const redirectResponse = (res, history) => {
  //For some reason the redirect response shows as 200 so we must compare the responseURL pathname to current window pathname
  if (res.status === 200) {
    if (res.request.responseURL.indexOf("/api/") === -1) {
      const responseURL = new URL(res.request.responseURL);
      if (responseURL.pathname !== window.location.pathname) {
        history.replace(responseURL.pathname);
      }
    }
  }
};

export const redirectNotFound = () => {
  //Redirect if trying to access an id that doesn't exist, this may cause unwanted redirects
  const split = window.location.pathname.split("/");
  if (!isNaN(Number(split.pop()))) {
    const redirect = split.join("/");
    history.push(redirect);
  }
};
