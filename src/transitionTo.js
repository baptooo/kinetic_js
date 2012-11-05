Kinetic.Node.prototype.transitionTo = function(config) {
    if(!this.transAnim) {
        this.transAnim = new Kinetic.Animation();
    }
    /*
     * create new transition
     */
    var node = this.nodeType === 'Stage' ? this : this.getLayer();
    var that = this;
    var trans = new Kinetic.Transition(this, config);

    this.transAnim.func = function(frame) {
    	// TODO: transitionTo evolution, adding step callback function
    	config.step && config.step.apply(config, Array.prototype.slice.call(arguments).concat([frame.time / (config.duration * 1000)]));
        trans._onEnterFrame();
    };
    this.transAnim.node = node;

    // subscribe to onFinished for first tween
    trans.onFinished = function() {
        // remove animation
        that.transAnim.stop();
        that.transAnim.node.draw();

        // callback
        if(config.callback) {
            config.callback();
        }
    };
    // auto start
    trans.start();
    this.transAnim.start();
    return trans;
};

Kinetic.Global.extend(Kinetic.Circle, {
	transitionTo: null
});