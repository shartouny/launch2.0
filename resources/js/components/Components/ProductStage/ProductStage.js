import React, { Component } from 'react';

/**
 *
 */
export default class ProductStage extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };
  /**
   *
   * @param {[]} location
   */
  static getFirstLocation(location) {
    if (!location.length) {
      return;
    }

    if (location[0].preview) {
      return location[0].preview;
    }
  };
  /**
   *
   * @param {number} locationId
   * @returns {{}}
   */
  getSubLocation(locationId) {
    const { selectedStage } = this.props;
    let preview = {};

    selectedStage.subLocationSettings.forEach(location => {
      if (location.blankStageLocationSubId === locationId) {
        preview = location.preview;
      }
    });

    return preview;
  };
  /**
   *
   * @param {{}} selectedStage
   * @param {number} distanceId
   * @returns {{topOffsetPercent: number}}
   */
  getByDistanceId = (selectedStage, distanceId) => {
    let yDistance = { topOffsetPercent: 0 };

    selectedStage.subLocationSettings.forEach(location => {
      location.offsets.forEach(offset => {
        if (offset.id === distanceId) {
          yDistance = offset;
        }
      });
    });

    return yDistance;
  };
  /**
   *
   * @param {{}} selectedStage
   * @param {number} selectedLocationId
   * @returns {*}
   */
  getFirstDistanceByLocation = (selectedStage, selectedLocationId) => {
    const selectedDistance = selectedStage.subLocationSettings.find(location => location.blankStageLocationSubId === selectedLocationId);

    if (!selectedDistance || !selectedDistance.offsets.length) {
      return { topOffsetPercent: 0 };
    }

    return selectedDistance.offsets[0];
  };
  /**
   *
   * @param {number} selectedLocationId
   * @param {number} distanceId
   * @returns {{}}
   */
  getSubDistance(selectedLocationId, distanceId) {
    //console.log('getSubDistance', selectedLocationId, distanceId);
    const { selectedStage } = this.props;
    let previewOffset = {};

    /**
     * If no locations are selected but distances are switching get by distance id.
     */
    if (!selectedLocationId && distanceId) {
      return this.getByDistanceId(selectedStage, distanceId)
    }

    /**
     * No distance selected but there might be one (which we don't display)
     */
    if (!distanceId) {
      return this.getFirstDistanceByLocation(selectedStage, selectedLocationId);
    }

    selectedStage.subLocationSettings.forEach(location => {
      if (location.blankStageLocationSubId === selectedLocationId) {
        if (location.offsets && location.offsets.length) {
          location.offsets.forEach(offset => {
            if (offset.blankStageLocationSubOffsetId === distanceId) {
              previewOffset = offset;
            }
          });
        }
      }
    });

    return previewOffset;
  };
  /**
   *
   * @returns {number}
   */
  getFirstDistance() {
    const { selectedStage } = this.props;
    let yDistance = 0;

    if (
      selectedStage.subLocationSettings
      && selectedStage.subLocationSettings.length
    ) {
      if (
        selectedStage.subLocationSettings[0].offsets
        && selectedStage.subLocationSettings[0].offsets.length
      ) {
        yDistance = selectedStage.subLocationSettings[0].offsets[0].topOffsetPercent
      }
    }

    return Number(yDistance);
  };
  /**
   *
   * @param {{}} preview
   * @returns {{x: *, width: *, y: *, height: *}}
   */
  static getCoordinates(preview) {
    return {
      x: preview.left,
      y: preview.top,
      height: preview.height,
      width: preview.width,
    };
  };
  /**
   *
   * @returns {null|*}
   */
  render() {
    const {
      artwork,
      selectedStage,
      selectedLocationId,
      selectedOffsetId,
      product
    } = this.props;
    const {
      subLocationSettings = [],
    } = selectedStage;
    let location;
    let yDistance = 0;

    if (!selectedLocationId || selectedLocationId.length === 0) {
      console.log('no selectedLocationId');
      location = ProductStage.getFirstLocation(subLocationSettings);
    } else {
      console.log('getSubLocation');
      location = this.getSubLocation(selectedLocationId);
    }
    console.log('location',location);

    if (selectedOffsetId) {
      const { topOffsetPercent } = this.getSubDistance(selectedLocationId, selectedOffsetId);
      yDistance = Number(topOffsetPercent || 0);
    } else {
      yDistance = this.getFirstDistance();
    }
    console.log('yDistance',yDistance);

    const MAX_WIDTH = 500;
    const MAX_HEIGHT = 500;

    /**
     * TODO no locations fallback?
     */
    if (!location) {
     // console.error('no location');
      return null;
    }

    const {
      x = 0,
      y = 0,
      width = 0,
      height = 0,
    } = ProductStage.getCoordinates(location);
    const xPercent = Number(x) / 100;
    const yPercent = (Number(y) + yDistance) / 100;
    const xCord = xPercent * MAX_WIDTH;
    const yCord = yPercent * MAX_HEIGHT;
    const widthPixels = parseInt((width * MAX_WIDTH) / 100).toFixed(2);
    const heightPixels = parseInt((height * MAX_HEIGHT) / 100).toFixed(2);

   // console.log('ProductStage.getCoordinates', ProductStage.getCoordinates(location));

    return (
      <div style={{
        position: 'absolute',
        zIndex: 0,
        left: 0,
        top: 0,
        bottom:0,
        right: 0,
        minWidth: `500px`,
        maxWidth: `${MAX_WIDTH}px`,
      }}>
       <div
         className="responsive"
         style={{
           transform: `translate(${xCord}px, ${yCord}px)`,
         }}
       >
         <img
           src={artwork.thumbUrl}
           alt={artwork.fileName}
           className={`responsive ${product.isSilhouetteArtwork ? 'grayscale-filter' : ''}`}
           style={{
             maxHeight: `${MAX_HEIGHT}px`,
             width: `${widthPixels}px`,
             height: `${heightPixels}px`,
             objectFit: 'contain',
             objectPosition: 'center top',
             position:'absolute',
             top:0,
             left:0
           }}
         />
       </div>
      </div>
    );
  }
}
