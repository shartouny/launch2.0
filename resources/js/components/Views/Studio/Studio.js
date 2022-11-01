import React from 'react'

function StudioIntegration(props) {
  const {requirements} = props
  const studioURL = process.env.MIX_STUDIO_URL
  return (
    <div
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        overflow: 'hidden',
        background: '#f4f5fa'
      }}
    >
      <iframe
        src={`${studioURL}?config=${requirements}`}
        width={'100%'}
        height={'100%'}
        frameBorder="0"
      />
    </div>
  )
}

export default StudioIntegration
